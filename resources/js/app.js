import './bootstrap';
import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
    Alpine.data('cartPage', (totals = {}) => ({
        couponCode: totals.coupon_code ?? '',
        couponApplied: Boolean(totals.coupon_code),
        couponMessage: '',
        couponError: false,
        subtotal: totals.subtotal ?? 0,
        discount: totals.discount ?? 0,
        total: totals.total ?? 0,
        itemCount: totals.item_count ?? 0,
        formatMoney(n) {
            return Number(n).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },
        async applyCoupon() {
            if (!this.couponCode.trim()) return;
            this.couponMessage = '';
            try {
                const res = await fetch('/cart/coupon', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ coupon_code: this.couponCode }),
                });
                const data = await res.json();
                if (data.success) {
                    this.couponApplied = true;
                    this.couponError = false;
                    this.couponMessage = data.message;
                    this.subtotal = data.totals.subtotal;
                    this.discount = data.totals.discount;
                    this.total = data.totals.total;
                    this.itemCount = data.totals.item_count;
                } else {
                    this.couponError = true;
                    this.couponMessage = data.message || 'Invalid coupon.';
                }
            } catch {
                this.couponError = true;
                this.couponMessage = 'Something went wrong. Please try again.';
            }
        },
        async removeCoupon() {
            try {
                const res = await fetch('/cart/coupon', {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await res.json();
                if (data.success) {
                    this.couponApplied = false;
                    this.couponCode = '';
                    this.couponError = false;
                    this.couponMessage = data.message;
                    this.subtotal = data.totals.subtotal;
                    this.discount = data.totals.discount;
                    this.total = data.totals.total;
                }
            } catch {
                this.couponError = true;
                this.couponMessage = 'Could not remove coupon.';
            }
        },
    }));

    Alpine.data('checkoutWizard', (config = {}) => ({
        step: config.step ?? 1,
        useNewAddress: config.useNewAddress ?? false,
        shippingMethod: config.shippingMethod ?? 'standard',
        paymentMethod: config.paymentMethod ?? 'cod',
        codShippingPayment: config.codShippingPayment ?? 'bkash',
        paymentReference: config.paymentReference ?? '',
        subtotal: config.subtotal ?? 0,
        discount: config.discount ?? 0,
        shipping: config.shipping ?? 0,
        total: config.total ?? 0,
        freeShippingEnabled: config.freeShippingEnabled ?? false,
        freeShippingThreshold: config.freeShippingThreshold ?? 500,
        currencySymbol: config.currencySymbol ?? '৳',
        gatewayRedirect: config.gatewayRedirect ?? {},
        formatMoney(n) {
            return Number(n).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },
        dueOnDelivery() {
            return Math.max(0, this.subtotal - this.discount);
        },
        updateShipping(key, rate) {
            this.shipping = (this.freeShippingEnabled && this.subtotal >= this.freeShippingThreshold) ? 0 : rate;
            this.total = Math.max(0, this.subtotal - this.discount + this.shipping);
        },
        validateBeforeSubmit() {
            if (['bkash', 'nagad'].includes(this.paymentMethod)
                && !this.gatewayRedirect[this.paymentMethod]
                && !this.paymentReference.trim()) {
                alert('Please enter your payment reference number.');
                this.step = 3;
                return false;
            }
            if (this.paymentMethod === 'stripe' && !this.gatewayRedirect.stripe) {
                alert('Stripe is not available. Choose another payment method.');
                this.step = 3;
                return false;
            }
            if (this.paymentMethod === 'cod' && this.shipping > 0) {
                if (!this.codShippingPayment) {
                    alert('Please select bKash or Nagad to pay the shipping charge.');
                    this.step = 3;
                    return false;
                }
                if (!this.paymentReference.trim()) {
                    alert('Please pay the shipping charge first and enter the transaction ID.');
                    this.step = 3;
                    return false;
                }
            }
            return true;
        },
    }));
});

window.Alpine = Alpine;
Alpine.start();
