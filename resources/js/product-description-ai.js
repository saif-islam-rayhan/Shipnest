/**
 * AI product description — ghost text inside Quill, Tab to accept / Esc to dismiss.
 */
export function bindProductDescriptionAi(quill, options = {}) {
    const editor = document.getElementById('quill-editor');
    const form = options.form || editor?.closest('form');
    const url = options.url || editor?.dataset.generateUrl;
    const wrap = document.querySelector('[data-description-ai]');
    const statusEl = wrap?.querySelector('[data-ai-status]');
    const generateBtn = wrap?.querySelector('[data-ai-generate]');

    if (!quill || !editor || !form || !url) {
        return;
    }

    const qlContainer = editor.querySelector('.ql-container') || editor;
    if (getComputedStyle(qlContainer).position === 'static') {
        qlContainer.style.position = 'relative';
    }

    let ghost = qlContainer.querySelector('[data-ai-ghost]');
    if (!ghost) {
        ghost = document.createElement('div');
        ghost.setAttribute('data-ai-ghost', '');
        ghost.className = 'product-ai-ghost hidden';
        ghost.innerHTML = `
            <div data-ai-suggestion-text class="product-ai-ghost-text"></div>
            <p class="product-ai-ghost-hint">Tab to insert · Esc to dismiss</p>
        `;
        qlContainer.appendChild(ghost);
    }

    const suggestionText = ghost.querySelector('[data-ai-suggestion-text]');

    let pending = '';
    let loading = false;
    let lastFingerprint = '';

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            || form.querySelector('input[name="_token"]')?.value
            || '';
    }

    function selectedLabel(select) {
        if (!select) return '';
        const opt = select.options[select.selectedIndex];
        return opt && opt.value ? opt.text.trim() : '';
    }

    function collectPayload() {
        const attrs = [];
        const nameInputs = form.querySelectorAll('input[name^="attributes"][name$="[name]"]');
        const valueInputs = form.querySelectorAll('input[name^="attributes"][name$="[value]"]');
        nameInputs.forEach((nameInput, i) => {
            const valueInput = valueInputs[i];
            const name = nameInput.value?.trim();
            const value = valueInput?.value?.trim();
            if (name && value) {
                attrs.push({ name, value });
            }
        });

        const priceInput = form.querySelector('input[name="variants[0][price]"]')
            || form.querySelector('input[name="price"]')
            || form.querySelector('input[name*="[price]"]');

        return {
            name: form.querySelector('input[name="name"]')?.value?.trim() || '',
            category: selectedLabel(form.querySelector('select[name="category_id"]')),
            brand: selectedLabel(form.querySelector('select[name="brand_id"]')),
            sku: form.querySelector('input[name="sku"]')?.value?.trim() || '',
            short_description: form.querySelector('textarea[name="short_description"]')?.value?.trim() || '',
            price: priceInput?.value || null,
            attributes: attrs,
        };
    }

    function fingerprint(payload) {
        return JSON.stringify(payload);
    }

    function editorIsEmpty() {
        const text = (quill.getText() || '').replace(/\s+/g, ' ').trim();
        return text === '' || text === '\n';
    }

    function syncHiddenInput() {
        const input = document.getElementById('description-input');
        if (input) {
            input.value = quill.root.innerHTML;
        }
    }

    function clearSuggestion() {
        pending = '';
        ghost.classList.add('hidden');
        if (suggestionText) {
            suggestionText.textContent = '';
        }
        editor.classList.remove('has-ai-ghost');
    }

    function showSuggestion(text) {
        if (!text || !editorIsEmpty()) {
            clearSuggestion();
            return;
        }
        pending = text;
        if (suggestionText) {
            suggestionText.textContent = text;
        }
        ghost.classList.remove('hidden');
        editor.classList.add('has-ai-ghost');
        if (statusEl) {
            statusEl.textContent = 'Tab to insert · Esc to dismiss';
        }
    }

    function acceptSuggestion() {
        if (!pending) return;
        const html = pending
            .split(/\n{2,}/)
            .map((p) => `<p>${escapeHtml(p).replace(/\n/g, '<br>')}</p>`)
            .join('');
        quill.root.innerHTML = html;
        syncHiddenInput();
        clearSuggestion();
        if (statusEl) {
            statusEl.textContent = 'Description inserted';
            setTimeout(() => {
                if (statusEl.textContent === 'Description inserted') {
                    statusEl.textContent = '';
                }
            }, 2000);
        }
        quill.focus();
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function requestSuggestion(force = false) {
        const payload = collectPayload();
        if (!payload.name) {
            if (statusEl) statusEl.textContent = 'Enter product name first';
            return;
        }

        if (!force && !editorIsEmpty()) {
            return;
        }

        const fp = fingerprint(payload);
        if (!force && fp === lastFingerprint && pending) {
            showSuggestion(pending);
            return;
        }

        if (loading) return;
        loading = true;
        if (statusEl) statusEl.textContent = 'Generating…';
        if (generateBtn) generateBtn.disabled = true;
        ghost.classList.add('hidden');

        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Generation failed');
            }

            lastFingerprint = fp;
            showSuggestion(data.description || '');
        } catch (e) {
            clearSuggestion();
            if (statusEl) statusEl.textContent = e.message || 'Could not generate description';
        } finally {
            loading = false;
            if (generateBtn) generateBtn.disabled = false;
        }
    }

    quill.root.addEventListener('focus', () => {
        if (editorIsEmpty()) {
            requestSuggestion(false);
        }
    });

    quill.on('text-change', () => {
        if (!editorIsEmpty() && pending) {
            clearSuggestion();
        }
        syncHiddenInput();
    });

    quill.root.addEventListener('keydown', (e) => {
        if (e.key === 'Tab' && pending) {
            e.preventDefault();
            e.stopPropagation();
            acceptSuggestion();
            return;
        }
        if (e.key === 'Escape' && pending) {
            e.preventDefault();
            clearSuggestion();
            if (statusEl) statusEl.textContent = '';
        }
    }, true);

    document.addEventListener('keydown', (e) => {
        if (!pending) return;
        const inEditor = document.activeElement?.closest('#quill-editor, [data-description-ai]');
        if (e.key === 'Tab' && inEditor) {
            e.preventDefault();
            acceptSuggestion();
        }
        if (e.key === 'Escape') {
            clearSuggestion();
            if (statusEl) statusEl.textContent = '';
        }
    });

    generateBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (!editorIsEmpty()) {
            if (!confirm('Replace current description with AI suggestion?')) {
                return;
            }
            quill.setText('');
            syncHiddenInput();
        }
        requestSuggestion(true);
    });

    ghost.addEventListener('click', () => {
        quill.focus();
    });
}
