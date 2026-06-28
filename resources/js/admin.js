/* Admin panel charts — Chart.js via CDN */
window.initAdminLineChart = function (id) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: JSON.parse(el.dataset.labels || '[]'),
            datasets: [{ data: JSON.parse(el.dataset.values || '[]'), borderColor: '#F57C00', backgroundColor: 'rgba(245,124,0,0.1)', fill: true, tension: 0.3 }],
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });
};
window.initAdminDonutChart = function (id) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: JSON.parse(el.dataset.labels || '[]'),
            datasets: [{ data: JSON.parse(el.dataset.values || '[]'), backgroundColor: ['#F57C00', '#1A237E', '#4CAF50', '#2196F3', '#9C27B0', '#F44336'] }],
        },
        options: { responsive: true },
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

    if (input?.value) {
        productQuillInstance.root.innerHTML = input.value;
    }
};

document.addEventListener('alpine:init', () => {
    Alpine.data('productWizard', (config = {}) => ({
        step: 1,
        variants: config.variants?.length ? config.variants : [{ name: 'Default', sku: '', price: '', compare_price: '', stock: 0, weight: '' }],
        attributes: config.attributes?.length ? config.attributes : [],
        existingImages: config.existingImages || [],
        previews: [],
        mainImage: 0,
        previewFiles(e) {
            Array.from(e.target.files).forEach((file) => {
                this.previews.push({ url: URL.createObjectURL(file), file });
            });
        },
        handleDrop(e) {
            const input = e.target.closest('div').querySelector('input[type=file]');
            if (input && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                this.previewFiles({ target: input });
            }
        },
        setMain(i) {
            this.mainImage = i;
        },
        syncQuill() {
            const input = document.getElementById('description-input');
            if (productQuillInstance && input) {
                input.value = productQuillInstance.root.innerHTML;
            }
        },
    }));
});

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
