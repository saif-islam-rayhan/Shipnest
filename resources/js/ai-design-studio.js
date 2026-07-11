/** Alpine component: merchant AI Mode studio (design / search / bestsellers / market / trends) */
export function registerAiDesignStudio(Alpine) {
    Alpine.data('aiDesignStudio', (config = {}) => ({
        generateUrl: config.generateUrl || '',
        createProductUrl: config.createProductUrl || '',
        prompt: '',
        loading: false,
        error: '',
        messages: [],
        mode: 'design',
        modes: [
            { id: 'design', label: 'Design with AI' },
            { id: 'search', label: 'Product search' },
            { id: 'bestsellers', label: 'Analyze bestsellers' },
            { id: 'market', label: 'Evaluate market potential' },
            { id: 'trends', label: 'Discover trends' },
        ],

        selectMode(id) {
            this.mode = id;
            this.error = '';
        },

        fillExample(text) {
            this.prompt = text;
        },

        placeholder() {
            return ({
                design: 'Describe your needs… e.g. Design Labubu mug',
                search: 'Search products… e.g. smart watch under 2000',
                bestsellers: 'Optional filter… or just send to see your top sellers',
                market: 'Evaluate a niche… e.g. wireless earbuds for students',
                trends: 'Discover trends… e.g. fashion june 2026',
            })[this.mode] || 'Describe your needs…';
        },

        loadingLabel() {
            return ({
                design: 'Designing your product…',
                search: 'Searching ShipNest catalog…',
                bestsellers: 'Analyzing your bestsellers…',
                market: 'Evaluating market potential…',
                trends: 'Discovering trends…',
            })[this.mode] || 'Working…';
        },

        canSend() {
            const text = (this.prompt || '').trim();
            if (this.mode === 'bestsellers' || this.mode === 'trends') {
                return true;
            }
            return text.length >= 2;
        },

        createProductLink(imageUrl) {
            const url = new URL(this.createProductUrl, window.location.origin);
            url.searchParams.set('design_image', imageUrl);
            return url.toString();
        },

        async send() {
            if (this.loading || !this.canSend()) return;

            const text = (this.prompt || '').trim();
            const display = text || ({
                bestsellers: 'Show my bestsellers',
                trends: 'Discover trending products',
            })[this.mode] || text;

            this.error = '';
            this.messages.push({ role: 'user', text: display });
            this.prompt = '';
            this.loading = true;
            this.$nextTick(() => this.scrollThread());

            try {
                const res = await fetch(this.generateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ prompt: text, mode: this.mode }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || data.error || 'Request failed');
                }
                this.messages.push({
                    role: 'assistant',
                    text: data.description || 'Done.',
                    image_url: data.image_url || null,
                    products: Array.isArray(data.products) ? data.products : [],
                });
            } catch (e) {
                this.error = e.message || 'Something went wrong';
                this.messages.push({
                    role: 'assistant',
                    text: 'Sorry — that request failed. ' + (e.message || 'Try again.'),
                    products: [],
                });
            } finally {
                this.loading = false;
                this.$nextTick(() => this.scrollThread());
            }
        },

        scrollThread() {
            const el = this.$refs.thread;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },
    }));
}
