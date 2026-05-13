/**
 * Plugin global de Chart.js que dibuja el porcentaje sobre cada porción
 * de los gráficos tipo "pie" y "doughnut".
 *
 * Las porciones cuyo porcentaje sea menor al umbral (4%) no muestran etiqueta
 * para evitar superposición. Se puede desactivar por gráfico con
 * options.plugins.percentLabels.enabled = false.
 */
(function () {
    if (typeof Chart === 'undefined') return;

    const plugin = {
        id: 'percentLabels',
        afterDatasetsDraw(chart, _args, pluginOpts) {
            if (chart.config.type !== 'pie' && chart.config.type !== 'doughnut') return;
            const opts = Object.assign({
                enabled: true,
                color: '#fff',
                font: 'bold 12px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                minPercent: 4,
                shadow: true,
            }, pluginOpts || {});
            if (!opts.enabled) return;

            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data) return;

            const dataset = chart.data.datasets[0];
            if (!dataset || !dataset.data) return;
            const total = dataset.data.reduce((a, b) => a + (Number(b) || 0), 0);
            if (total <= 0) return;

            ctx.save();
            ctx.font = opts.font;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            meta.data.forEach((arc, i) => {
                const value = Number(dataset.data[i]) || 0;
                if (value <= 0) return;
                const pct = (value / total) * 100;
                if (pct < opts.minPercent) return;

                const { x, y, startAngle, endAngle, innerRadius, outerRadius } = arc.getProps(
                    ['x', 'y', 'startAngle', 'endAngle', 'innerRadius', 'outerRadius'],
                    true
                );
                const mid = (startAngle + endAngle) / 2;
                const r = (innerRadius + outerRadius) / 2;
                const px = x + Math.cos(mid) * r;
                const py = y + Math.sin(mid) * r;
                const txt = pct.toFixed(pct >= 10 ? 0 : 1) + '%';

                if (opts.shadow) {
                    ctx.shadowColor = 'rgba(0,0,0,0.55)';
                    ctx.shadowBlur = 3;
                }
                ctx.fillStyle = opts.color;
                ctx.fillText(txt, px, py);
            });

            ctx.restore();
        },
    };

    Chart.register(plugin);
})();
