/* Merchant panel JS — Chart.js and Quill loaded via CDN in layouts/merchant.blade.php */
import { bindProductDescriptionAi } from './product-description-ai';

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

    window.productQuillInstance = quillInstance;

    if (input?.value) {
        quillInstance.root.innerHTML = input.value;
    }

    bindProductDescriptionAi(quillInstance, {
        url: editor.dataset.generateUrl,
        form: editor.closest('form'),
    });
};

function bootProductQuillWhenReady() {
    if (!document.getElementById('quill-editor') || !document.getElementById('description-input')) {
        return;
    }
    if (typeof Quill === 'undefined') {
        setTimeout(bootProductQuillWhenReady, 40);
        return;
    }
    window.initProductQuill();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProductQuillWhenReady);
} else {
    bootProductQuillWhenReady();
}
