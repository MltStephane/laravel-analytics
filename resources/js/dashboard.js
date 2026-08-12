(function () {
    var chart = document.querySelector('[data-chart]');

    if (!chart) {
        return;
    }

    var tooltip = chart.querySelector('.chart-tooltip');
    var points = chart.querySelectorAll('[data-chart-point]');
    var toggles = chart.parentNode.querySelectorAll('[data-series-toggle]');

    function showTooltip(point) {
        tooltip.textContent = point.getAttribute('data-tooltip');
        tooltip.hidden = false;
    }

    function hideTooltip() {
        tooltip.hidden = true;
    }

    Array.prototype.forEach.call(points, function (point) {
        point.addEventListener('mouseenter', function () { showTooltip(point); });
        point.addEventListener('mouseleave', hideTooltip);
        point.addEventListener('focus', function () { showTooltip(point); });
        point.addEventListener('blur', hideTooltip);
        point.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideTooltip();
            }
        });
    });

    Array.prototype.forEach.call(toggles, function (toggle) {
        toggle.addEventListener('click', function () {
            var key = toggle.getAttribute('data-series-toggle');
            var layer = document.getElementById('chart-series-' + key);
            var visible = toggle.getAttribute('aria-pressed') !== 'true';
            var layerPoints = layer.querySelectorAll('[data-chart-point]');

            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            layer.classList.toggle('is-hidden', !visible);
            layer.setAttribute('aria-hidden', visible ? 'false' : 'true');
            Array.prototype.forEach.call(layerPoints, function (point) {
                point.setAttribute('tabindex', visible ? '0' : '-1');
            });
        });
    });
}());
