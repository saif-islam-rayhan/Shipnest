/**
 * Fill product review form with an AI-generated positive review.
 */
export function bindProductReviewAi() {
    document.querySelectorAll('[data-review-ai]').forEach((wrap) => {
        if (wrap.dataset.bound === '1') {
            return;
        }
        wrap.dataset.bound = '1';

        const form = wrap.closest('form');
        const url = wrap.dataset.generateUrl;
        const btn = wrap.querySelector('[data-ai-generate]');
        const statusEl = wrap.querySelector('[data-ai-status]');

        if (!form || !url || !btn) {
            return;
        }

        btn.addEventListener('click', async () => {
            const rating = form.querySelector('[name="rating"]');
            const title = form.querySelector('[name="title"]');
            const body = form.querySelector('[name="body"]');
            if (!title || !body) {
                return;
            }

            btn.disabled = true;
            if (statusEl) {
                statusEl.textContent = 'Generating…';
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            || form.querySelector('input[name="_token"]')?.value
                            || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Failed to generate review');
                }

                if (rating && data.rating) {
                    rating.value = String(data.rating);
                }
                title.value = data.title || '';
                body.value = data.body || '';
                if (statusEl) {
                    statusEl.textContent = 'Ready — edit if you want, then submit';
                }
            } catch (e) {
                if (statusEl) {
                    statusEl.textContent = e.message || 'Could not generate. Try again.';
                }
            } finally {
                btn.disabled = false;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    bindProductReviewAi();
});
