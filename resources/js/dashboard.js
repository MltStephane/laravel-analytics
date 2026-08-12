(function () {
    var chart = document.querySelector('[data-chart]');

    if (!chart) {
        return;
    }

    var tooltip = chart.querySelector('.chart-tooltip');
    var points = chart.querySelectorAll('[data-chart-point]');
    var toggles = chart.parentNode.querySelectorAll('[data-series-toggle]');
    var svg = chart.querySelector('svg');
    var viewBoxParts = svg.getAttribute('viewBox').split(' ');
    var viewBoxWidth = parseFloat(viewBoxParts[2]);

    function buildTooltip(point) {
        tooltip.textContent = '';

        var title = document.createElement('div');
        title.className = 'chart-tooltip-title';
        title.textContent = point.getAttribute('data-label');
        tooltip.appendChild(title);

        var rows = [
            ['Pages vues', 'data-pageviews', 'pv'],
            ['Visiteurs uniques', 'data-visitors', 'vis']
        ];

        for (var i = 0; i < rows.length; i++) {
            var row = document.createElement('div');
            row.className = 'chart-tooltip-row';

            var dot = document.createElement('span');
            dot.className = 'chart-tooltip-dot ' + rows[i][2];
            dot.setAttribute('aria-hidden', 'true');
            row.appendChild(dot);

            var name = document.createElement('span');
            name.textContent = rows[i][0];
            row.appendChild(name);

            var value = document.createElement('span');
            value.className = 'chart-tooltip-value';
            value.textContent = point.getAttribute(rows[i][1]);
            row.appendChild(value);

            tooltip.appendChild(row);
        }
    }

    function positionTooltip(point) {
        var svgRect = svg.getBoundingClientRect();
        var scale = svgRect.width / viewBoxWidth;
        var cx = parseFloat(point.getAttribute('cx')) * scale;
        var cy = parseFloat(point.getAttribute('cy')) * scale;

        tooltip.hidden = false;

        var width = tooltip.offsetWidth;
        var height = tooltip.offsetHeight;
        // Le tooltip est positionné par rapport au cadre du conteneur défilant ;
        // on retranche scrollLeft pour le placer sur le point tel qu'affiché.
        var viewportX = cx - chart.scrollLeft;
        var maxLeft = chart.clientWidth - width - 6;

        var left = viewportX + 14;
        if (left > maxLeft) {
            left = viewportX - width - 14;
        }
        if (left < 6) {
            left = 6;
        }

        var top = cy - height - 10;
        if (top < 6) {
            top = cy + 14;
        }

        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }

    function showTooltip(point) {
        buildTooltip(point);
        positionTooltip(point);
    }

    function hideTooltip() {
        tooltip.hidden = true;
    }

    Array.prototype.forEach.call(points, function (point) {
        point.addEventListener('mouseenter', function () { showTooltip(point); });
        point.addEventListener('mouseleave', hideTooltip);
    });

    Array.prototype.forEach.call(toggles, function (toggle) {
        toggle.addEventListener('click', function () {
            var key = toggle.getAttribute('data-series-toggle');
            var layer = document.getElementById('chart-series-' + key);
            var visible = toggle.getAttribute('aria-pressed') !== 'true';

            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            layer.classList.toggle('is-hidden', !visible);
            layer.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });
    });
}());
