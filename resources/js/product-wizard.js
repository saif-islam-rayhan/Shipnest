/** Shared Alpine component for admin/merchant multi-step product forms. */
export function registerProductWizard(Alpine, getQuillInstance = () => null) {
    Alpine.data('productWizard', (config = {}) => ({
        step: 1,
        variants: config.variants?.length
            ? config.variants
            : [{ name: 'Default', sku: '', price: '', compare_price: '', stock: 0, weight: '' }],
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
            const input = e.target.closest('div')?.querySelector('input[type=file]');
            if (input && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                this.previewFiles({ target: input });
            }
        },
        setMain(i) {
            this.mainImage = i;
        },
        syncQuill() {
            const quill = getQuillInstance();
            const input = document.getElementById('description-input');
            if (quill && input) {
                input.value = quill.root.innerHTML;
            }
        },
        submitForm(e) {
            this.syncQuill();
            const form = e.target;
            if (!form.checkValidity()) {
                e.preventDefault();
                if (!form.merchant_id?.value || !form.name?.value || !form.category_id?.value || !form.sku?.value) {
                    this.step = 1;
                } else if (!form.querySelector('[name="variants[0][price]"]')?.value) {
                    this.step = 3;
                }
                form.reportValidity();

                return false;
            }

            return true;
        },
    }));
}
