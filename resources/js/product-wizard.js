/** Shared Alpine component for admin/merchant multi-step product forms. */
export function registerProductWizard(Alpine, getQuillInstance = () => null) {
    Alpine.data('productWizard', (config = {}) => ({
        step: 1,
        formError: '',
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
        fieldValue(form, name) {
            const el = form.querySelector(`[name="${name}"]`);
            return (el?.value ?? '').toString().trim();
        },
        validateBeforeSubmit(form) {
            const hasMerchant = Boolean(form.querySelector('[name="merchant_id"]'));
            if (hasMerchant && !this.fieldValue(form, 'merchant_id')) {
                return { step: 1, message: 'Please select a merchant / shop.' };
            }
            if (!this.fieldValue(form, 'name')) {
                return { step: 1, message: 'Product name is required.' };
            }
            if (!this.fieldValue(form, 'category_id')) {
                return { step: 1, message: 'Please select a category.' };
            }
            if (!this.fieldValue(form, 'sku')) {
                return { step: 1, message: 'SKU is required.' };
            }

            const missingPrice = this.variants.some((v) => {
                const price = v.price;
                return price === '' || price === null || price === undefined || Number.isNaN(Number(price));
            });
            if (!this.variants.length || missingPrice) {
                return { step: 3, message: 'Please enter a sale price on the Pricing step.' };
            }

            return null;
        },
        submitForm(e) {
            this.syncQuill();
            this.formError = '';

            const form = e.target;
            const error = this.validateBeforeSubmit(form);
            if (error) {
                e.preventDefault();
                this.step = error.step;
                this.formError = error.message;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            return true;
        },
    }));
}
