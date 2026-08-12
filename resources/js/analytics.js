/*
 * laravel-analytics browser tracker (vanilla JS, ES5-compatible).
 * Privacy-first: no cookies, only a localStorage uuid.
 */
(function (window, document, navigator) {
    'use strict';

    var SCRIPT = document.currentScript;
    var ENDPOINT = SCRIPT ? SCRIPT.getAttribute('data-endpoint') : null;
    var AUTO_TRACK = !(SCRIPT && SCRIPT.getAttribute('data-auto-track') === 'false');
    var STORAGE_KEY = 'analytics.uuid';
    var throttleTimer = null;

    /* ---------------------------------------------------------------- */
    /* Client uuid (localStorage, no cookies)                            */
    /* ---------------------------------------------------------------- */
    function randomUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function getUuid() {
        try {
            var stored = window.localStorage.getItem(STORAGE_KEY);
            if (stored && stored.length <= 64) {
                return stored;
            }
        } catch (e) { /* storage unavailable: fall back to a volatile id */ }

        var uuid;
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            uuid = window.crypto.randomUUID();
        } else {
            uuid = randomUuid();
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, uuid);
        } catch (e) { /* ignore quota/private mode errors */ }

        return uuid;
    }

    /* ---------------------------------------------------------------- */
    /* Send                                                              */
    /* ---------------------------------------------------------------- */
    function send(payload) {
        var body;
        try {
            body = JSON.stringify(payload);
        } catch (e) {
            return;
        }

        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };

        if (navigator.sendBeacon && ENDPOINT) {
            try {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(ENDPOINT, blob)) {
                    return;
                }
            } catch (e) { /* fall through to fetch */ }
        }

        if (window.fetch && ENDPOINT) {
            window.fetch(ENDPOINT, {
                method: 'POST',
                headers: headers,
                body: body,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () { /* fire and forget */ });
            return;
        }

        /* Last resort: hidden image is not usable for POST, use XHR. */
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ENDPOINT, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(body);
        } catch (e) { /* ignore */ }
    }

    /* ---------------------------------------------------------------- */
    /* Payload helpers                                                   */
    /* ---------------------------------------------------------------- */
    function truncate(str, max) {
        return String(str).slice(0, max);
    }

    function cleanData(data) {
        var out = {};
        var count = 0;
        for (var key in data) {
            if (Object.prototype.hasOwnProperty.call(data, key) && count < 50) {
                out[truncate(key, 100)] = truncate(String(data[key]), 500);
                count++;
            }
        }
        return out;
    }

    function getLanguage() {
        return (navigator.language || '').slice(0, 20);
    }

    function trackPageview() {
        send({
            type: 'pageview',
            uuid: getUuid(),
            url: window.location.pathname + window.location.search,
            referrer: document.referrer || null,
            title: truncate(document.title, 255),
            hostname: window.location.hostname,
            language: getLanguage()
        });
    }

    function trackEvent(name, data) {
        send({
            type: 'event',
            name: truncate(name, 50),
            url: window.location.pathname + window.location.search,
            data: cleanData(data || {})
        });
    }

    /* ---------------------------------------------------------------- */
    /* Auto-tracking (SPA-aware)                                         */
    /* ---------------------------------------------------------------- */
    function doNotTrack() {
        return navigator.doNotTrack === '1' || navigator.doNotTrack === 'yes'
            || window.doNotTrack === '1';
    }

    function initAutoTracking() {
        if (AUTO_TRACK && !doNotTrack()) {
            trackPageview();

            var onChange = function () {
                if (throttleTimer) {
                    return;
                }
                throttleTimer = setTimeout(function () {
                    throttleTimer = null;
                    trackPageview();
                }, 300);
            };

            if (window.history && window.history.pushState) {
                var pushState = window.history.pushState;
                window.history.pushState = function () {
                    var result = pushState.apply(this, arguments);
                    onChange();
                    return result;
                };
            }
            window.addEventListener('popstate', onChange);
            window.addEventListener('hashchange', onChange);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutoTracking);
    } else {
        initAutoTracking();
    }

    /* ---------------------------------------------------------------- */
    /* Click delegation: [data-analytics] elements                       */
    /* ---------------------------------------------------------------- */
    document.addEventListener('click', function (event) {
        var target = event.target;
        while (target && target !== document) {
            if (target.getAttribute && target.getAttribute('data-analytics')) {
                var name = target.getAttribute('data-analytics');
                var data = {};
                for (var i = 0; i < target.attributes.length; i++) {
                    var attr = target.attributes[i];
                    if (attr.name.indexOf('data-analytics-') === 0) {
                        data[attr.name.slice('data-analytics-'.length)] = attr.value;
                    }
                }
                trackEvent(name, data);
                return;
            }
            target = target.parentNode;
        }
    });

    /* ---------------------------------------------------------------- */
    /* Public API                                                        */
    /* ---------------------------------------------------------------- */
    window.analytics = {
        track: function (name, data) {
            trackEvent(name, data || {});
        },
        pageview: function (url, title) {
            send({
                type: 'pageview',
                uuid: getUuid(),
                url: truncate(url || window.location.pathname + window.location.search, 2048),
                title: truncate(title || document.title, 255),
                referrer: document.referrer || null,
                hostname: window.location.hostname,
                language: getLanguage()
            });
        },
        identify: function (id) {
            try {
                window.localStorage.setItem(STORAGE_KEY, truncate(id, 64));
            } catch (e) { /* ignore */ }
        }
    };
})(window, document, navigator);
