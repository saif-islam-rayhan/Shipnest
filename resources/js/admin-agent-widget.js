/**
 * ShipNest agent widget (public + admin FAB).
 */
window.AdminAgentWidget = (function () {
    const instances = new Map();
    const PREVIEW_COUNT = 4;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function placeholderImg(name) {
        return 'https://placehold.co/400x400/f3f4f6/6b7280/png?text=' + encodeURIComponent((name || 'Product').substring(0, 12));
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function matchLabel(p) {
        if (p.match_label) return p.match_label;
        if (p.section === 'trending') return 'Trending on ShipNest';
        if (p.source === 'platform') return 'Available on ShipNest';
        return 'On ShipNest';
    }

    function getEls(root) {
        return {
            feed: root.querySelector('[data-agent-feed]'),
            input: root.querySelector('[data-agent-input]'),
            sendBtn: root.querySelector('[data-agent-send]'),
            micBtn: root.querySelector('[data-agent-mic]'),
            welcome: root.querySelector('[data-agent-welcome]'),
            attachBtn: root.querySelector('[data-agent-attach]'),
            imageInput: root.querySelector('[data-agent-image-input]'),
            imagePreview: root.querySelector('[data-agent-image-preview]'),
            sendUrl: root.dataset.sendUrl || '',
            resetUrl: root.dataset.resetUrl || '',
            bootstrapUrl: root.dataset.bootstrapUrl || '',
            cartUrl: root.dataset.cartUrl || '',
            cartPageUrl: root.dataset.cartPageUrl || '/cart',
            csrf: root.dataset.csrf || '',
            adminMode: root.dataset.adminMode === '1',
        };
    }

    function renderImagePreviews(root) {
        const state = instances.get(root);
        if (!state?.imagePreview) return;

        if (!state.pendingImages?.length) {
            state.imagePreview.classList.add('hidden');
            state.imagePreview.innerHTML = '';
            return;
        }

        state.imagePreview.classList.remove('hidden');
        state.imagePreview.innerHTML = state.pendingImages.map((file, idx) => `
            <div class="admin-agent-image-chip">
                <img src="${URL.createObjectURL(file)}" alt="${esc(file.name)}">
                <button type="button" data-remove-image="${idx}" title="Remove">×</button>
            </div>
        `).join('');

        state.imagePreview.querySelectorAll('[data-remove-image]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.pendingImages.splice(Number(btn.dataset.removeImage), 1);
                renderImagePreviews(root);
            });
        });
    }

    function getSpeechRecognition() {
        return window.SpeechRecognition || window.webkitSpeechRecognition || null;
    }

    function stopVoiceListening(root) {
        const state = instances.get(root);
        if (!state) return;

        if (state.recognition) {
            try {
                state.recognition.onresult = null;
                state.recognition.onerror = null;
                state.recognition.onend = null;
                state.recognition.stop();
            } catch (_) {
                // ignore
            }
            state.recognition = null;
        }

        state.listening = false;
        state.micBtn?.classList.remove('is-listening');
        if (state.micBtn) {
            state.micBtn.title = 'Voice input';
            state.micBtn.setAttribute('aria-pressed', 'false');
        }
    }

    function toggleVoiceInput(root) {
        const state = instances.get(root);
        if (!state?.micBtn) return;

        if (state.listening) {
            stopVoiceListening(root);
            return;
        }

        const SpeechRecognition = getSpeechRecognition();
        if (!SpeechRecognition) {
            alert('Voice input এই browser-এ সাপোর্ট করে না। Chrome বা Edge ব্যবহার করুন।');
            return;
        }

        const recognition = new SpeechRecognition();
        recognition.lang = 'bn-BD';
        recognition.interimResults = true;
        recognition.continuous = false;
        recognition.maxAlternatives = 1;

        let finalTranscript = '';
        const defaultPlaceholder = state.adminMode ? 'Ask or tap mic...' : 'বলুন বা লিখুন — mic চাপুন...';

        recognition.onstart = () => {
            state.listening = true;
            state.micBtn.classList.add('is-listening');
            state.micBtn.title = 'Listening… tap to stop';
            state.micBtn.setAttribute('aria-pressed', 'true');
            if (state.input) {
                state.input.placeholder = 'শুনছি… কথা বলুন';
            }
        };

        recognition.onresult = (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interim += transcript;
                }
            }

            if (state.input) {
                state.input.value = (finalTranscript || interim).trim();
            }
        };

        recognition.onerror = (event) => {
            stopVoiceListening(root);
            if (state.input) {
                state.input.placeholder = defaultPlaceholder;
            }
            if (event.error === 'not-allowed') {
                alert('Microphone permission দিন — browser address bar-এ mic allow করুন।');
            } else if (event.error !== 'aborted' && event.error !== 'no-speech') {
                alert('Voice input ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
            }
        };

        recognition.onend = () => {
            const text = (finalTranscript || state.input?.value || '').trim();
            stopVoiceListening(root);
            if (state.input) {
                state.input.placeholder = defaultPlaceholder;
            }
            if (text) {
                if (state.input) state.input.value = text;
                sendQuery(root, text);
            }
        };

        state.recognition = recognition;
        try {
            recognition.start();
        } catch (_) {
            stopVoiceListening(root);
            alert('Voice input শুরু করা যায়নি। আবার চেষ্টা করুন।');
        }
    }

    function bindRoot(root) {
        if (!root || instances.has(root)) return;

        const els = getEls(root);
        instances.set(root, {
            ...els,
            welcomeTemplate: els.welcome?.outerHTML || '',
            bootstrapped: false,
            turnId: 0,
            lastPlatformProducts: [],
            sectionProductsStore: new Map(),
            pendingImages: [],
            lastCreatedProductId: null,
            listening: false,
            recognition: null,
        });

        els.sendBtn?.addEventListener('click', () => sendQuery(root));
        els.input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendQuery(root);
            }
        });

        els.micBtn?.addEventListener('click', () => toggleVoiceInput(root));
        if (els.micBtn && !getSpeechRecognition()) {
            els.micBtn.disabled = true;
            els.micBtn.title = 'Voice not supported in this browser';
        }

        els.attachBtn?.addEventListener('click', () => els.imageInput?.click());
        els.imageInput?.addEventListener('change', () => {
            const state = instances.get(root);
            const files = Array.from(els.imageInput.files || []);
            if (!files.length || !state) return;
            state.pendingImages = (state.pendingImages || []).concat(files).slice(0, 5);
            els.imageInput.value = '';
            renderImagePreviews(root);
        });

        bindWelcomeChips(root);

        const resetBtn = root.closest('.admin-agent-fab-root')?.querySelector('[data-agent-widget-reset]');
        resetBtn?.addEventListener('click', () => resetChat(root));
    }

    function bindWelcomeChips(root) {
        root.querySelectorAll('[data-agent-welcome] .follow-chip').forEach((btn) => {
            btn.addEventListener('click', () => handleChipClick(root, btn.dataset.query));
        });
    }

    function restoreWelcome(root) {
        const state = instances.get(root);
        if (!state?.feed || !state.welcomeTemplate) return;

        state.feed.insertAdjacentHTML('beforeend', state.welcomeTemplate);
        state.welcome = root.querySelector('[data-agent-welcome]');
        bindWelcomeChips(root);
    }

    function handleChipClick(root, query) {
        sendQuery(root, query);
    }

    function scrollBottom(root) {
        const { feed } = instances.get(root) || getEls(root);
        if (feed) feed.scrollTop = feed.scrollHeight;
    }

    function renderUserBubbleContent(message, imageFiles) {
        const files = imageFiles || [];
        let html = '';
        if (files.length) {
            html += '<div class="user-chat-images">';
            files.forEach((file) => {
                html += `<img src="${URL.createObjectURL(file)}" alt="${esc(file.name)}">`;
            });
            html += '</div>';
        }
        const text = (message || '').trim();
        if (text) {
            html += esc(text);
        } else if (files.length) {
            html += `📷 ${files.length} image(s)`;
        }
        return html;
    }

    function renderBubble(role, html, wide = false) {
        const row = document.createElement('div');
        row.className = `admin-agent-row ${role}`;
        row.innerHTML = `<div class="admin-agent-bubble ${role}${wide ? ' wide' : ''}">${html}</div>`;
        return row;
    }

    function renderTrendingList(products) {
        const items = products.map((p, i) => {
            const price = p.estimated_price || p.price_label
                ? `<span class="agent-trending-price">${esc(p.estimated_price || p.price_label)}</span>`
                : '';
            return `<li class="agent-trending-item">
                <span class="agent-trending-num">${i + 1}.</span>
                <span class="agent-trending-name">${esc(p.product_name || p.name)}</span>
                ${price}
            </li>`;
        }).join('');
        return `<ol class="agent-trending-list">${items}</ol>`;
    }

    function renderProductCard(p, idx, tid, adminMode) {
        const imgUrl = p.image || placeholderImg(p.name);
        const price = p.price_label ? `<p class="agent-ai-card-price">${esc(p.price_label)}</p>` : '';
        const productId = p.id || p.product_id;
        let actionBtn = '';
        if (!adminMode && productId) {
            actionBtn = `<button type="button" class="agent-ai-card-cart" data-card-cart data-product-id="${productId}">Add to cart</button>`;
        } else if (adminMode && productId && p.admin_url) {
            actionBtn = `<a href="${esc(p.admin_url)}" class="agent-ai-card-cart agent-ai-card-cart--link">Edit in Admin</a>`;
        } else if (p.url) {
            actionBtn = `<a href="${esc(p.url)}" target="_blank" rel="noopener" class="agent-ai-card-cart agent-ai-card-cart--link">View →</a>`;
        }

        return `<div class="agent-ai-card" data-card-idx="${idx}" data-turn="${tid}">
            <div class="agent-ai-card-img-wrap">
                <img src="${esc(imgUrl)}" alt="${esc(p.name)}" loading="lazy" data-card-img
                    onerror="this.onerror=null;this.src='${placeholderImg(p.name)}'">
                <button type="button" class="agent-ai-card-view" data-card-view aria-label="View product" title="View product">↗</button>
            </div>
            <p class="agent-ai-match-line">${esc(matchLabel(p))}</p>
            <p class="agent-ai-card-title">${esc(p.name)}</p>
            ${price}
            ${actionBtn}
        </div>`;
    }

    function renderCarouselHtml(products, tid, sectionKey, adminMode) {
        const cards = products.map((p, i) => renderProductCard(p, i, `${tid}-${sectionKey}`, adminMode)).join('');
        return `<div class="agent-carousel-wrap" data-carousel-wrap data-section="${sectionKey}">
            <button type="button" class="agent-carousel-nav prev hidden" data-carousel-prev aria-label="Previous">‹</button>
            <div class="agent-product-carousel" data-carousel data-section="${sectionKey}">${cards}</div>
            <button type="button" class="agent-carousel-nav next" data-carousel-next aria-label="Next">›</button>
        </div>`;
    }

    function renderProductSection(title, allProducts, previewCount, tid, sectionKey, adminMode) {
        if (!allProducts?.length) return '';

        const preview = allProducts.slice(0, previewCount);
        const remaining = allProducts.length - preview.length;

        let html = `<div class="agent-ai-product-section" data-product-section="${sectionKey}">`;
        html += `<h4 class="agent-ai-subheading">${esc(title)}</h4>`;
        html += renderCarouselHtml(preview, tid, sectionKey, adminMode);
        if (remaining > 0) {
            html += `<button type="button" class="agent-see-more-btn" data-see-more data-section="${sectionKey}" data-turn="${tid}">
                See more (${remaining} more)
            </button>`;
        }
        html += '</div>';
        return html;
    }

    async function addProductToCart(root, product, btn) {
        const state = instances.get(root);
        const productId = product?.id || product?.product_id;
        if (!productId || !state?.cartUrl) return;

        const name = product.name || 'this product';
        if (!confirm(`"${name}" cart-এ add করতে চান?`)) return;

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Adding...';
        }

        try {
            const res = await fetch(state.cartUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': state.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Cart add failed');
            }
            if (btn) {
                btn.textContent = '✓ Added';
                setTimeout(() => {
                    btn.textContent = 'Add to cart';
                    btn.disabled = false;
                }, 2000);
            }
        } catch (e) {
            alert(e.message || 'Cart-এ add করা যায়নি।');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Add to cart';
            }
        }
    }

    function openProduct(product) {
        if (product?.url) {
            window.open(product.url, '_blank', 'noopener');
        }
    }

    function bindCardEvents(root, card, product) {
        card.querySelector('[data-card-view]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openProduct(product);
        });
        card.querySelector('[data-card-img]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openProduct(product);
        });
        card.querySelector('[data-card-cart]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            addProductToCart(root, product, e.currentTarget);
        });
    }

    function bindCarouselWrap(root, wrap, products, turn) {
        const carousel = wrap.querySelector('[data-carousel]');
        const prev = wrap.querySelector('[data-carousel-prev]');
        const next = wrap.querySelector('[data-carousel-next]');
        if (!carousel) return;

        const scrollAmt = 148;
        function updateNav() {
            if (!prev || !next) return;
            prev.classList.toggle('hidden', carousel.scrollLeft <= 4);
            next.classList.toggle('hidden', carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 4);
        }
        prev?.addEventListener('click', () => carousel.scrollBy({ left: -scrollAmt, behavior: 'smooth' }));
        next?.addEventListener('click', () => carousel.scrollBy({ left: scrollAmt, behavior: 'smooth' }));
        carousel.addEventListener('scroll', updateNav);
        setTimeout(updateNav, 100);

        wrap.querySelectorAll('.agent-ai-card').forEach((card, i) => {
            const product = products[i];
            if (product) bindCardEvents(root, card, product);
        });
    }

    function bindProductSections(root, turn, tid, msg) {
        const state = instances.get(root);
        if (!state) return;

        const searchAll = msg.products_all?.length ? msg.products_all : (msg.products || []);
        const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);

        if (searchAll.length) {
            state.sectionProductsStore.set(`${tid}-search`, searchAll);
            const searchWrap = turn.querySelector('[data-carousel-wrap][data-section="search"]');
            if (searchWrap) {
                bindCarouselWrap(root, searchWrap, searchAll.slice(0, msg.products_preview_count || PREVIEW_COUNT), turn);
            }
        }

        if (trendingAll.length) {
            state.sectionProductsStore.set(`${tid}-trending`, trendingAll);
            const trendingWrap = turn.querySelector('[data-carousel-wrap][data-section="trending"]');
            if (trendingWrap) {
                bindCarouselWrap(root, trendingWrap, trendingAll.slice(0, msg.trending_preview_count || PREVIEW_COUNT), turn);
            }
        }

        turn.querySelectorAll('[data-see-more]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const section = btn.dataset.section;
                const storeKey = `${tid}-${section}`;
                const all = state.sectionProductsStore.get(storeKey) || [];
                const previewCount = section === 'trending'
                    ? (msg.trending_preview_count || PREVIEW_COUNT)
                    : (msg.products_preview_count || PREVIEW_COUNT);
                const remaining = all.slice(previewCount);
                const carousel = turn.querySelector(`[data-carousel][data-section="${section}"]`);
                const wrap = turn.querySelector(`[data-carousel-wrap][data-section="${section}"]`);
                if (!carousel || !remaining.length) {
                    btn.remove();
                    return;
                }

                const startIdx = carousel.querySelectorAll('.agent-ai-card').length;
                remaining.forEach((product, i) => {
                    const holder = document.createElement('div');
                    holder.innerHTML = renderProductCard(product, startIdx + i, `${tid}-${section}`, state.adminMode);
                    const card = holder.firstElementChild;
                    carousel.appendChild(card);
                    bindCardEvents(root, card, product);
                });

                btn.remove();
                wrap?.querySelector('[data-carousel-next]')?.classList.remove('hidden');
                carousel.dispatchEvent(new Event('scroll'));
            });
        });
    }

    function renderAgentTextBlock(msg, className = 'agent-ai-greeting') {
        if (!msg.greeting && !msg.show_content) {
            return '';
        }
        if (!msg.content_html && !msg.content) {
            return '';
        }

        const html = msg.content_html || esc(msg.content).replace(/\n/g, '<br>');
        return `<div class="${className}">${html}</div>`;
    }

    function isPlatformTrendingCatalog(msg, trendingAll) {
        return msg.type === 'platform'
            && !(trendingAll?.length)
            && (msg.catalog_mode === 'trending' || msg.query === 'trending product');
    }

    function renderTurnBody(root, query, msg) {
        const state = instances.get(root) || getEls(root);
        const q = query || msg.query || '';
        const hasProducts = msg.products?.length > 0;
        const hasTrending = msg.trending_products?.length > 0;
        const inst = instances.get(root);
        const tid = inst ? ++inst.turnId : 1;

        let body = '';

        if (hasProducts && msg.type === 'trending') {
            body += `<h3 class="agent-ai-heading">${esc(capitalize(q))}</h3>`;
            if (msg.summary) {
                body += `<p class="agent-ai-summary">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
            }
            body += renderTrendingList(msg.products);
        } else if (msg.type === 'platform' && (hasProducts || hasTrending)) {
            const previewCount = msg.products_preview_count || PREVIEW_COUNT;
            const searchAll = msg.products_all?.length ? msg.products_all : (msg.products || []);
            const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);
            const trendingPreview = msg.trending_preview_count || PREVIEW_COUNT;
            const isTrendingCatalog = isPlatformTrendingCatalog(msg, trendingAll);

            if (msg.greeting || msg.show_content || msg.catalog_mode === 'image_search') {
                body += renderAgentTextBlock(msg);
            } else if (!isTrendingCatalog && (hasProducts || hasTrending)) {
                body += `<h3 class="agent-ai-heading">${esc(capitalize(q))}</h3>`;
            }

            if (msg.summary && (!msg.greeting || !msg.show_content) && msg.catalog_mode !== 'image_search') {
                body += `<p class="agent-ai-summary">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
            }

            if (hasProducts) {
                const searchTitle = isTrendingCatalog
                    ? `🔥 Trending Products (${msg.total_count || searchAll.length})`
                    : `Results on ShipNest (${msg.total_count || searchAll.length})`;

                body += renderProductSection(
                    searchTitle,
                    searchAll,
                    previewCount,
                    tid,
                    'search',
                    state.adminMode,
                );
            }

            if (trendingAll.length > 0) {
                const trendingTitle = hasProducts
                    ? `Related trending (${msg.trending_total_count || trendingAll.length})`
                    : (msg.catalog_mode === 'image_search'
                        ? `Similar products (${msg.trending_total_count || trendingAll.length})`
                        : `Related products (${msg.trending_total_count || trendingAll.length})`);

                body += renderProductSection(
                    trendingTitle,
                    trendingAll,
                    trendingPreview,
                    tid,
                    'trending',
                    state.adminMode,
                );
            }
        } else if (hasProducts) {
            body += `<h3 class="agent-ai-heading">${esc(capitalize(q))}</h3>`;
            body += renderCarouselHtml(msg.products, tid, 'search', state.adminMode);
        }

        if (!hasProducts && !hasTrending && (msg.content_html || msg.content)) {
            const text = msg.content_html || esc(msg.content).replace(/\n/g, '<br>');
            body += text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        } else if (!body && msg.summary) {
            body += esc(msg.summary).replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        } else if (msg.summary && hasProducts && msg.type !== 'platform' && msg.type !== 'trending') {
            body += `<p class="agent-ai-summary">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
        }

        if (msg.type === 'product_create_success' && msg.product) {
            if (msg.product.store_url) {
                body += `<a href="${esc(msg.product.store_url)}" class="admin-agent-link-btn">View Product →</a>`;
            }
            if (msg.product.admin_url && state.adminMode) {
                body += `<a href="${esc(msg.product.admin_url)}" class="admin-agent-link-btn" style="margin-left:.5rem">Open in Admin →</a>`;
            }
        }

        if (msg.type === 'product_image_updated' && msg.product?.admin_url && state.adminMode) {
            body += `<a href="${esc(msg.product.admin_url)}" class="admin-agent-link-btn">Open in Admin →</a>`;
        }

        if (msg.cart_url && (msg.type === 'cart_success' || msg.type === 'cart_contents')) {
            body += `<a href="${esc(msg.cart_url)}" class="admin-agent-link-btn">Open Cart Page →</a>`;
        }
        if (msg.checkout_url && msg.type === 'cart_contents') {
            body += `<a href="${esc(msg.checkout_url)}" class="admin-agent-link-btn" style="margin-left:.5rem">Checkout →</a>`;
        }

        if (msg.follow_ups?.length) {
            body += '<div class="admin-agent-follow">';
            msg.follow_ups.forEach((fq) => {
                body += `<button type="button" class="follow-chip" data-query="${esc(fq)}">${esc(fq)}</button>`;
            });
            body += '</div>';
        }

        return { body, tid, wide: hasProducts || hasTrending };
    }

    function renderTurn(root, query, msg, userImages) {
        const turn = document.createElement('div');
        turn.className = 'admin-agent-turn';
        turn.appendChild(renderBubble('user', renderUserBubbleContent(query, userImages)));

        const { body, tid, wide } = renderTurnBody(root, query, msg);
        const agentBubble = renderBubble('agent', body, wide);
        turn.appendChild(agentBubble);

        turn.querySelectorAll('.follow-chip').forEach((btn) => {
            btn.addEventListener('click', () => handleChipClick(root, btn.dataset.query));
        });

        if (msg.type === 'platform') {
            bindProductSections(root, turn, tid, msg);
            const state = instances.get(root);
            if (state) {
                const searchAll = msg.products_all?.length ? msg.products_all : (msg.products || []);
                const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);
                state.lastPlatformProducts = [...searchAll, ...trendingAll];
            }
        } else if (msg.type !== 'trending' && msg.products?.length > 0) {
            const wrap = turn.querySelector('[data-carousel-wrap]');
            if (wrap) {
                bindCarouselWrap(root, wrap, msg.products, turn);
            }
        }

        return turn;
    }

    function appendTurn(root, query, msg, userImages) {
        const state = instances.get(root);
        if (!state) return;

        state.welcome?.remove();
        const turn = renderTurn(root, query, msg, userImages);
        state.feed?.appendChild(turn);
        scrollBottom(root);
    }

    function showTyping(root, query, userImages) {
        const state = instances.get(root);
        if (!state?.feed) return;

        const turn = document.createElement('div');
        turn.className = 'admin-agent-turn';
        turn.dataset.typing = '1';
        turn.appendChild(renderBubble('user', renderUserBubbleContent(query, userImages)));
        const typingLabel = (userImages?.length || String(query).startsWith('📷'))
            ? 'Analyzing image...'
            : 'Searching...';
        turn.appendChild(renderBubble('agent typing', `<span>${typingLabel}</span>`));
        state.feed.appendChild(turn);
        scrollBottom(root);
    }

    function hideTyping(root) {
        const state = instances.get(root);
        state?.feed?.querySelector('[data-typing="1"]')?.remove();
    }

    async function sendQuery(root, text) {
        const state = instances.get(root);
        if (!state) return;

        const message = (text || state.input?.value || '').trim();
        const imagesToSend = (state.pendingImages || []).slice();
        if (!message && !imagesToSend.length) return;

        const payload = { message };
        if (state.lastPlatformProducts.length > 0) {
            payload.context_products = state.lastPlatformProducts.map((p) => ({
                id: p.id || p.product_id || null,
                product_id: p.id || p.product_id || null,
                name: p.name,
            }));
        }

        const displayQuery = message || (imagesToSend.length ? `📷 ${imagesToSend.length} image(s)` : '');

        if (state.input) state.input.value = '';
        if (state.sendBtn) state.sendBtn.disabled = true;
        showTyping(root, message || displayQuery, imagesToSend);

        try {
            let res;
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': state.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (imagesToSend.length > 0) {
                const form = new FormData();
                form.append('message', message);
                const attachIntent = /\b(add image|upload image|attach image|image add|image upload|photo add|ছবি যোগ|ছবি দাও|ইমেজ যোগ|image দাও)\b/i.test(message);
                if (state.adminMode && state.lastCreatedProductId && attachIntent) {
                    form.append('product_id', String(state.lastCreatedProductId));
                }
                imagesToSend.forEach((file) => form.append('images[]', file));
                if (payload.context_products) {
                    payload.context_products.forEach((p, i) => {
                        Object.entries(p).forEach(([k, v]) => {
                            if (v != null) form.append(`context_products[${i}][${k}]`, String(v));
                        });
                    });
                }
                res = await fetch(state.sendUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: form,
                });
            } else {
                res = await fetch(state.sendUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        ...headers,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
            }

            hideTyping(root);
            if (!res.ok) {
                const errBody = await res.json().catch(() => null);
                const errMsg = errBody?.message
                    || (res.status === 419 ? 'Session expired — page refresh করে আবার চেষ্টা করুন।' : null)
                    || `Request failed (${res.status}). Please try again.`;
                appendTurn(root, message || displayQuery, { content: errMsg, type: 'error' }, imagesToSend);
                return;
            }
            const data = await res.json();
            if (!data.type && data.meta?.type) data.type = data.meta.type;
            if (!data.cart_url && data.meta?.cart_url) data.cart_url = data.meta.cart_url;
            if (data.type === 'product_create_success' && data.product?.id) {
                state.lastCreatedProductId = data.product.id;
            }
            if (data.type === 'product_image_updated' && data.product?.id) {
                state.lastCreatedProductId = data.product.id;
            }
            state.pendingImages = [];
            renderImagePreviews(root);
            appendTurn(root, message || displayQuery, data, imagesToSend);
        } catch {
            hideTyping(root);
            appendTurn(root, message || displayQuery, { content: 'Request failed. Please try again.', type: 'error' }, imagesToSend);
        } finally {
            if (state.sendBtn) state.sendBtn.disabled = false;
            state.input?.focus();
        }
    }

    async function resetChat(root) {
        const state = instances.get(root);
        if (!state) return;

        stopVoiceListening(root);

        const resetBtn = root.closest('.admin-agent-fab-root')?.querySelector('[data-agent-widget-reset]');
        if (resetBtn) resetBtn.disabled = true;

        try {
            const res = await fetch(state.resetUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': state.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) {
                throw new Error('reset failed');
            }

            state.lastPlatformProducts = [];
            state.sectionProductsStore.clear();
            state.pendingImages = [];
            state.lastCreatedProductId = null;
            state.turnId = 0;
            if (state.input) state.input.value = '';
            if (state.feed) state.feed.innerHTML = '';
            renderImagePreviews(root);
            restoreWelcome(root);
            state.bootstrapped = true;
            scrollBottom(root);
        } catch {
            appendTurn(root, 'system', { content: 'Chat reset ব্যর্থ। আবার চেষ্টা করুন।', type: 'error' });
        } finally {
            if (resetBtn) resetBtn.disabled = false;
            state.input?.focus();
        }
    }

    async function bootstrap(root) {
        if (!root) {
            document.querySelectorAll('[data-admin-agent-root]').forEach(bindRoot);
            root = document.querySelector('[data-admin-agent-root]');
        }

        bindRoot(root);
        const state = instances.get(root);
        if (!state || state.bootstrapped) return;

        try {
            const res = await fetch(state.bootstrapUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': state.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return;

            const data = await res.json();
            let pendingUser = '';

            (data.messages || []).forEach((msg) => {
                if (msg.role === 'user') {
                    pendingUser = msg.content;
                } else if (msg.role === 'assistant') {
                    appendTurn(root, msg.query || pendingUser, msg);
                    pendingUser = '';
                }
            });

            if (!state.feed?.querySelector('.admin-agent-turn') && state.welcome) {
                state.welcome.style.display = '';
            } else {
                state.welcome?.remove();
            }

            state.bootstrapped = true;
            scrollBottom(root);
        } catch {
            // ignore
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-admin-agent-root]').forEach(bindRoot);
    });

    return { bootstrap, sendQuery, resetChat };
})();
