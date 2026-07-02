/* Admin panel charts — Chart.js via CDN */
window.initAdminLineChart = function (id) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;

    const ctx = el.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 280);
    gradient.addColorStop(0, 'rgba(245, 124, 0, 0.28)');
    gradient.addColorStop(1, 'rgba(245, 124, 0, 0.02)');

    new Chart(el, {
        type: 'line',
        data: {
            labels: JSON.parse(el.dataset.labels || '[]'),
            datasets: [{
                data: JSON.parse(el.dataset.values || '[]'),
                borderColor: '#F57C00',
                backgroundColor: gradient,
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#F57C00',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827',
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9CA3AF', maxTicksLimit: 8, font: { size: 11 } },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { color: '#9CA3AF', font: { size: 11 } },
                    border: { display: false },
                },
            },
        },
    });
};

window.initAdminDonutChart = function (id) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: JSON.parse(el.dataset.labels || '[]'),
            datasets: [{
                data: JSON.parse(el.dataset.values || '[]'),
                backgroundColor: ['#F57C00', '#1A237E', '#10B981', '#3B82F6', '#8B5CF6', '#EF4444', '#6B7280'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827',
                    padding: 10,
                    cornerRadius: 8,
                },
            },
        },
    });
};
window.initAdminQuill = function () {
    const editor = document.getElementById('quill-editor');
    const input = document.getElementById('content-input');
    if (!editor || typeof Quill === 'undefined') return;
    const q = new Quill(editor, { theme: 'snow' });
    if (input?.value) q.root.innerHTML = input.value;
    document.querySelector('form')?.addEventListener('submit', () => { if (input) input.value = q.root.innerHTML; });
};

let productQuillInstance = null;

window.initProductQuill = function () {
    const editor = document.getElementById('quill-editor');
    const input = document.getElementById('description-input');
    if (!editor || productQuillInstance || typeof Quill === 'undefined') return;

    productQuillInstance = new Quill(editor, {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    window.productQuillInstance = productQuillInstance;

    if (input?.value) {
        productQuillInstance.root.innerHTML = input.value;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.admin-datatable').forEach(el => {
        if (typeof $ !== 'undefined' && $.fn.DataTable) $(el).DataTable({ order: [] });
    });
    const sortable = document.getElementById('categorySortable');
    if (sortable && typeof Sortable !== 'undefined') {
        Sortable.create(sortable, {
            animation: 150,
            onEnd: () => {
                const order = [...sortable.querySelectorAll('[data-id]')].map(el => el.dataset.id);
                const form = document.getElementById('categoryReorderForm');
                if (form) {
                    form.querySelector('[name=order]').value = JSON.stringify(order);
                    form.submit();
                }
            },
        });
    }
});
