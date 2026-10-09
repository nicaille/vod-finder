import Chart from 'chart.js/auto';

const canvas = document.getElementById('api-statistics-chart');
const payload = document.getElementById('api-statistics-data');
if (canvas && payload) {
    const report = JSON.parse(payload.textContent);
    const colors = ['#ff8d45', '#60a5fa', '#34d399', '#c084fc'];
    const datasets = metric => report.datasets.map((item, index) => ({
        label: item.label,
        data: item[metric],
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length],
        borderWidth: 2,
        pointRadius: report.labels.length > 100 ? 0 : 2,
        pointHitRadius: 10,
        tension: 0,
    }));
    const chart = new Chart(canvas, {
        type: 'line',
        data: {labels: report.labels, datasets: datasets('requests')},
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {mode: 'index', intersect: false},
            plugins: {
                legend: {labels: {color: '#e2e8f0', usePointStyle: true}},
                tooltip: {callbacks: {label: item => `${item.dataset.label} : ${item.parsed.y.toLocaleString('fr-FR')}`}},
            },
            scales: {
                x: {
                    ticks: {
                        color: '#94a3b8', maxTicksLimit: 6, maxRotation: 0,
                        callback(value) {
                            const label = this.getLabelForValue(value);
                            return label.includes(':') ? [label.slice(0, 5), label.slice(11, 16)] : label.slice(0, 5);
                        },
                    },
                    grid: {color: '#ffffff0a'},
                },
                y: {beginAtZero: true, ticks: {color: '#94a3b8', precision: 0}, grid: {color: '#ffffff15'}},
            },
        },
    });
    document.getElementById('api-chart-metric')?.addEventListener('change', event => {
        const metric = event.target.value === 'failures' ? 'failures' : 'requests';
        chart.data.datasets = datasets(metric);
        canvas.setAttribute('aria-label', metric === 'failures' ? 'Échecs API par période' : 'Requêtes API par période');
        chart.update();
    });
}
