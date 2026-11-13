(function () {
    'use strict';
    window.SASOjtCharts = {
        decorate: function (root) {
            root.querySelectorAll('[data-bar-value]').forEach(function (bar) {
                const value = Number(bar.dataset.barValue);
                const max = Number(bar.dataset.barMax) || 100;
                bar.style.setProperty('--bar-width', Math.max(0, Math.min(100, value / max * 100)) + '%');
            });
        }
    };
}());