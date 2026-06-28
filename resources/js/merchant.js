/* Merchant panel JS — Chart.js and Quill loaded via CDN in layouts/merchant.blade.php */

window.initRevenueChart = function (canvasId) {
    const el = document.getElementById(canvasId);
    if (!el || typeof Chart === 'undefined') return;

    const labels = JSON.parse(el.dataset.labels || '[]');
    const data = JSON.parse(el.dataset.values || '[]');

    new Chart(el, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue',
                data,
                borderColor: '#F57C00',
                backgroundColor: 'rgba(245, 124, 0, 0.1)',
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
};

window.initBarChart = function (canvasId) {
    const el = document.getElementById(canvasId);
    if (!el || typeof Chart === 'undefined') return;

    const labels = JSON.parse(el.dataset.labels || '[]');
    const data = JSON.parse(el.dataset.values || '[]');

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Revenue',
                data,
                backgroundColor: '#1A237E',
            }],
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
        },
    });
};

window.initDonutChart = function (canvasId) {
    const el = document.getElementById(canvasId);
    if (!el || typeof Chart === 'undefined') return;

    const labels = JSON.parse(el.dataset.labels || '[]');
    const data = JSON.parse(el.dataset.values || '[]');

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: ['#F57C00', '#1A237E', '#4CAF50', '#2196F3', '#9C27B0', '#F44336'],
            }],
        },
        options: { responsive: true },
    });
};

let quillInstance = null;

window.initProductQuill = function () {
    const editor = document.getElementById('quill-editor');
    const input = document.getElementById('description-input');
    if (!editor || quillInstance || typeof Quill === 'undefined') return;

    quillInstance = new Quill(editor, {
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
        quillInstance.root.innerHTML = input.value;
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
            if (quillInstance && input) {
                input.value = quillInstance.root.innerHTML;
            }
        },
    }));
});
