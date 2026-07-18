@extends('layouts.pos')

@section('title', 'POS')

@section('content')
@php
    $categoriesPayload = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values();
@endphp

<div class="pos-no-print flex h-full max-h-dvh flex-col overflow-hidden"
     x-data="posApp(@js([
         'categories' => $categoriesPayload,
         'currencySymbol' => $currencySymbol,
         'taxRate' => $taxRate,
         'branchName' => $branchName,
         'counter' => $counter,
         'cashierName' => $cashier->name,
         'platformName' => $platformName,
         'routes' => [
             'products' => route('admin.pos.products'),
             'scan' => route('admin.pos.scan'),
             'customers' => route('admin.pos.customers'),
             'checkout' => route('admin.pos.checkout'),
             'held' => route('admin.pos.held'),
             'hold' => route('admin.pos.hold'),
             'deleteHeld' => url('/admin/pos/held'),
             'dashboard' => route('admin.dashboard'),
             'logout' => route('logout'),
         ],
         'csrf' => csrf_token(),
     ]))"
     @keydown.window="onKeydown($event)">

    {{-- ========== HEADER ========== --}}
    <header class="z-20 flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-slate-200 bg-white px-3 py-2 shadow-sm sm:px-4">
        <a :href="routes.dashboard"
           class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[13px] font-medium text-slate-600 hover:bg-slate-50">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>

        <div class="flex shrink-0 items-center gap-2 border-r border-slate-200 pr-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-[#2563eb] text-[11px] font-bold text-white">SN</div>
            <div class="text-[13px] font-bold text-slate-900">
                <span x-text="platformName"></span> POS
                <span class="font-medium text-slate-400">(v1.0)</span>
            </div>
        </div>

        <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 text-[13px]">
            <div><span class="text-slate-400">Branch:</span> <span class="font-semibold text-slate-800" x-text="branchName"></span></div>
            <div><span class="text-slate-400">Counter:</span> <span class="font-semibold text-slate-800" x-text="counter"></span></div>
            <div><span class="text-slate-400">Cashier:</span> <span class="font-semibold text-slate-800" x-text="cashierName"></span></div>
        </div>

        <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
            <div class="text-[12px] font-medium text-slate-600" x-text="clock"></div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Online
            </span>
            <button type="button" @click="syncProducts()"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[13px] font-medium text-slate-700 hover:bg-slate-50">
                <svg class="h-3.5 w-3.5" :class="syncing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Sync
            </button>
            <button type="button" @click="loadHeld()"
                    class="relative inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[13px] font-medium text-slate-700 hover:bg-slate-50">
                Held Sales
                <span x-show="heldCount > 0" x-cloak
                      class="absolute -right-1.5 -top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
                      x-text="heldCount"></span>
            </button>
            <form :action="routes.logout" method="POST" class="inline shrink-0">
                <input type="hidden" name="_token" :value="csrf">
                <button type="submit" class="rounded-md bg-rose-500 px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-rose-600">Logout</button>
            </form>
        </div>
    </header>

    <div class="flex min-h-0 flex-1 overflow-hidden">
        {{-- ========== PRODUCTS ========== --}}
        <section class="flex min-h-0 min-w-0 flex-1 flex-col px-3 pb-2 pt-3 lg:px-4">
            <div class="mb-2.5 flex shrink-0 items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input x-ref="searchInput" type="text" x-model="search" @input.debounce.300ms="page = 1; fetchProducts()"
                           @keydown.enter.prevent="onSearchEnter()"
                           placeholder="Scan barcode or search... (F2)"
                           class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                           autocomplete="off">
                </div>
                <div class="flex shrink-0 overflow-hidden rounded-lg border border-slate-200 shadow-sm">
                    <button type="button" @click="viewMode = 'grid'" title="Grid view"
                            class="pos-view-btn border-r border-slate-200"
                            :class="viewMode === 'grid' && 'is-active'">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/>
                        </svg>
                    </button>
                    <button type="button" @click="viewMode = 'list'" title="List view"
                            class="pos-view-btn"
                            :class="viewMode === 'list' && 'is-active'">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Categories --}}
            <div class="relative mb-2.5 flex shrink-0 items-center gap-1.5">
                <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto pos-scroll pb-0.5">
                    <button type="button" class="pos-cat-btn" :class="!categoryId && !popular && 'is-active'" @click="setCategory(null)">All</button>
                    <button type="button" class="pos-cat-btn" :class="popular && 'is-active'" @click="setCategory('popular')">Popular</button>
                    <template x-for="cat in visibleCategories" :key="cat.id">
                        <button type="button" class="pos-cat-btn" :class="categoryId === cat.id && 'is-active'" @click="setCategory(cat.id)" x-text="cat.name"></button>
                    </template>
                </div>
                <div class="relative shrink-0" @click.outside="showMoreCats = false">
                    <button type="button" @click="showMoreCats = !showMoreCats" class="pos-cat-btn inline-flex items-center gap-1"
                            :class="moreCategories.some(c => c.id === categoryId) && 'is-active'">
                        More
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="showMoreCats" x-cloak class="absolute right-0 z-30 mt-1 max-h-64 w-52 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl">
                        <template x-for="cat in moreCategories" :key="'m'+cat.id">
                            <button type="button" @click="setCategory(cat.id); showMoreCats = false"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-blue-50"
                                    :class="categoryId === cat.id && 'bg-blue-50 font-semibold text-blue-700'"
                                    x-text="cat.name"></button>
                        </template>
                        <p x-show="!moreCategories.length" class="px-3 py-2 text-xs text-slate-400">No more categories</p>
                    </div>
                </div>
            </div>

            {{-- Product grid --}}
            <div class="pos-scroll min-h-0 flex-1 overflow-y-auto rounded-xl border border-slate-200/80 bg-white p-1.5">
                <div x-show="loading" class="flex h-40 items-center justify-center text-sm text-slate-400">Loading products…</div>
                <div x-show="!loading && products.length === 0" class="flex h-40 items-center justify-center text-sm text-slate-400">No products found</div>

                <div x-show="!loading && products.length" x-cloak
                     :class="viewMode === 'grid'
                        ? 'grid grid-cols-4 gap-1.5 sm:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7'
                        : 'flex flex-col gap-1'">
                    <template x-for="product in products" :key="product.id">
                        <div class="pos-product-card"
                             :class="viewMode === 'list' && '!flex-row items-stretch'">
                            <div class="pos-thumb" :class="viewMode === 'list' && '!h-14 !w-14 shrink-0'">
                                <template x-if="product.image">
                                    <img :src="product.image" :alt="product.name" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!product.image">
                                    <div class="pos-thumb-letter flex h-full w-full items-center justify-center" x-text="product.initial"></div>
                                </template>
                                <span x-show="isInCart(product)"
                                      class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 text-white shadow ring-1 ring-white">
                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col px-1.5 pb-1.5 pt-1" :class="viewMode === 'list' && 'justify-center py-1'">
                                <div class="truncate text-[11px] font-semibold leading-tight text-slate-900" x-text="product.name" :title="product.name"></div>
                                <div class="mt-0.5 truncate text-[9px] leading-tight text-slate-400" x-text="'SKU: ' + (product.sku || '—')"></div>
                                <div class="mt-1 overflow-hidden rounded border border-slate-200 bg-white p-0.5">
                                    <svg class="pos-barcode mx-auto block max-w-full"
                                         :data-barcode="product.barcode || product.sku || ''"></svg>
                                </div>
                                <div class="mt-0.5 text-[12px] font-bold leading-tight text-slate-900" x-text="money(product.price)"></div>
                                <div class="mt-0.5 truncate text-[9px] leading-tight text-slate-500">
                                    <span x-text="'VAT ' + taxRate + '%'"></span>
                                    <span class="mx-0.5">·</span>
                                    <span :class="product.stock > 0 ? 'text-emerald-600' : 'text-rose-600'"
                                          x-text="product.stock > 0 ? ('In Stock - ' + product.stock) : 'Out of stock'"></span>
                                </div>
                                <button type="button" class="pos-add-btn mt-1" @click="addProduct(product)"
                                        :class="viewMode === 'list' && '!mt-0 !ml-auto !w-16 shrink-0'">Add</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-2 flex shrink-0 items-center justify-between text-[13px] text-slate-500">
                <span x-text="meta.total + ' products - page ' + meta.current_page + '/' + Math.max(meta.last_page, 1)"></span>
                <div class="flex items-center gap-1">
                    <button type="button" @click="goPage(meta.current_page - 1)" :disabled="meta.current_page <= 1"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-1 disabled:opacity-40">Prev</button>
                    <template x-for="p in pageButtons" :key="p">
                        <button type="button" @click="goPage(p)"
                                class="min-w-[32px] rounded-md border px-2 py-1 font-medium"
                                :class="p === meta.current_page ? 'border-[#2563eb] bg-[#2563eb] text-white' : 'border-slate-200 bg-white text-slate-700'"
                                x-text="p"></button>
                    </template>
                    <button type="button" @click="goPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-1 disabled:opacity-40">Next</button>
                </div>
            </div>
        </section>

        {{-- ========== CART ========== --}}
        <aside class="flex min-h-0 w-[320px] shrink-0 flex-col overflow-hidden border-l border-slate-200 bg-white shadow-[-4px_0_16px_rgba(15,23,42,0.04)] xl:w-[340px]">
            <div class="flex shrink-0 items-start justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                <div>
                    <div class="text-[10px] text-slate-400">Sale #</div>
                    <div class="font-mono text-[12px] font-bold text-slate-800" x-text="saleNumber"></div>
                </div>
                <button type="button" @click="openCustomer()" class="text-right">
                    <div class="text-[10px] text-slate-400">Customer</div>
                    <div class="text-[12px] font-semibold text-[#2563eb]">
                        <span x-text="customer ? customer.name : 'Walk-in Customer'"></span>
                        <span class="ml-1 text-[10px] font-medium underline">Change</span>
                    </div>
                </button>
            </div>

            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-3 py-1 text-[11px]">
                <button type="button" @click="showNoteModal = true" class="font-medium text-[#2563eb] hover:underline">+ Add Note</button>
                <button type="button" @click="clearCart()" class="font-medium text-rose-500 hover:underline">Clear Cart</button>
            </div>

            <div class="pos-scroll min-h-0 flex-1 overflow-y-auto">
                <table class="w-full text-left text-[11px]">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-[9px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-5 px-1.5 py-1.5 font-semibold">#</th>
                            <th class="px-1 py-1.5 font-semibold">Product</th>
                            <th class="px-1 py-1.5 text-center font-semibold">Qty</th>
                            <th class="px-1 py-1.5 text-right font-semibold">Price</th>
                            <th class="px-1 py-1.5 text-right font-semibold">Disc.</th>
                            <th class="px-1.5 py-1.5 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="cart.length === 0">
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-xs text-slate-400">Scan or add products to start</td>
                            </tr>
                        </template>
                        <template x-for="(item, index) in cart" :key="item.variant_id">
                            <tr class="border-t border-slate-100" :class="selectedIndex === index ? 'bg-blue-50' : 'hover:bg-slate-50'" @click="selectedIndex = index">
                                <td class="px-1.5 py-1.5 text-slate-400" x-text="index + 1"></td>
                                <td class="max-w-[90px] px-1 py-1.5">
                                    <div class="truncate font-semibold text-slate-800" x-text="item.name"></div>
                                    <div class="truncate text-[9px] text-slate-400" x-text="'SKU: ' + (item.sku || '—')"></div>
                                    <div class="truncate font-mono text-[9px] font-semibold text-blue-700" x-text="'BC: ' + (item.barcode || item.sku || '—')"></div>
                                </td>
                                <td class="px-0.5 py-1.5">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <button type="button" @click.stop="changeQty(index, -1)"
                                                class="flex h-5 w-5 items-center justify-center rounded border border-slate-200 text-slate-600 hover:bg-slate-100">−</button>
                                        <span class="w-4 text-center font-bold" x-text="item.quantity"></span>
                                        <button type="button" @click.stop="changeQty(index, 1)"
                                                class="flex h-5 w-5 items-center justify-center rounded border border-slate-200 text-slate-600 hover:bg-slate-100">+</button>
                                    </div>
                                </td>
                                <td class="px-1 py-1.5 text-right tabular-nums" x-text="money(item.unit_price)"></td>
                                <td class="px-1 py-1.5 text-right">
                                    <input type="number" min="0" step="0.01" x-model.number="item.discount"
                                           @click.stop
                                           class="w-11 rounded border border-slate-200 px-0.5 py-0.5 text-right text-[10px] text-rose-500 outline-none focus:border-blue-400">
                                </td>
                                <td class="px-1.5 py-1.5 text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <span class="font-bold tabular-nums" x-text="money(lineTotal(item))"></span>
                                        <button type="button" @click.stop="removeItem(index)" class="p-0.5 text-slate-300 hover:text-rose-500" title="Remove">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-[#f8fafc] px-3 py-2.5">
                <div class="space-y-0.5 text-[12px]">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-medium tabular-nums" x-text="money(totals.subtotal)"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Item Discount</span><span class="tabular-nums text-rose-500" x-text="'-' + money(totals.itemDiscount)"></span></div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Invoice Discount (<span x-text="invoiceDiscountPercent"></span>%)</span>
                        <span class="tabular-nums text-rose-500" x-text="'-' + money(totals.invoiceDiscount)"></span>
                    </div>
                    <div class="flex justify-between"><span class="text-slate-500">VAT (per product)</span><span class="tabular-nums" x-text="money(totals.tax)"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Rounding</span><span class="tabular-nums" x-text="money(totals.rounding)"></span></div>
                </div>

                <div class="mt-2 flex items-end justify-between rounded-md border border-blue-100 bg-blue-50/60 px-2.5 py-2">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-700">Total Payable</div>
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            Total Items: <span x-text="cart.length"></span> · Total Qty: <span x-text="totalQty"></span>
                        </div>
                    </div>
                    <div class="text-xl font-bold tabular-nums text-[#2563eb]" x-text="money(totals.total)"></div>
                </div>

                <button type="button" class="pos-pay-btn mt-2 !py-2.5 !text-sm" @click="openPay()" :disabled="cart.length === 0 || paying">
                    <span x-text="paying ? 'Processing…' : ('Pay ' + money(totals.total) + ' (F9)')"></span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>

                <div class="mt-1.5 grid grid-cols-3 gap-1">
                    <button type="button" @click="holdSale()" :disabled="cart.length === 0"
                            class="rounded-md border border-slate-200 bg-white py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">Hold (F8)</button>
                    <button type="button" @click="showDiscountModal = true" :disabled="cart.length === 0"
                            class="rounded-md border border-slate-200 bg-white py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">Discount (F6)</button>
                    <button type="button" @click="clearCart()" :disabled="cart.length === 0"
                            class="rounded-md border border-rose-200 bg-rose-50 py-1.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-100 disabled:opacity-40">Clear (F7)</button>
                </div>
            </div>
        </aside>
    </div>

    {{-- ========== FOOTER SHORTCUTS ========== --}}
    <footer class="pos-footer flex h-10 shrink-0 items-center gap-3 overflow-x-auto px-4 text-[11px] sm:gap-4">
        <span class="shrink-0"><kbd>F2</kbd> Search</span>
        <span class="shrink-0"><kbd>F4</kbd> Customer</span>
        <span class="shrink-0"><kbd>F6</kbd> Discount</span>
        <span class="shrink-0"><kbd>F8</kbd> Hold Sale</span>
        <span class="shrink-0"><kbd>F9</kbd> Pay Now</span>
        <span class="shrink-0"><kbd>F10</kbd> Complete</span>
        <span class="shrink-0"><kbd>Ctrl+P</kbd> Print</span>
        <span class="shrink-0"><kbd>Del</kbd> Remove</span>
        <span class="shrink-0"><kbd>Esc</kbd> Close</span>
        <a :href="routes.dashboard" class="ml-auto shrink-0 rounded bg-white/10 px-3 py-1 font-semibold text-white hover:bg-white/20">Exit POS</a>
    </footer>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-14 left-1/2 z-50 -translate-x-1/2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-xl"
         x-text="toast"></div>

    {{-- Print receipt (hidden) --}}
    <div id="pos-receipt-wrap" class="hidden">
        <div id="pos-receipt" class="mx-auto max-w-sm p-6 text-sm">
            <h2 class="text-center text-lg font-bold" x-text="platformName + ' POS'"></h2>
            <p class="text-center text-xs text-slate-500" x-text="branchName + ' · Counter ' + counter"></p>
            <p class="mt-2 text-center font-mono font-bold" x-text="completedOrders?.[0]?.order_number || saleNumber"></p>
            <hr class="my-3">
            <template x-for="item in (lastReceiptCart.length ? lastReceiptCart : cart)" :key="'r'+item.variant_id">
                <div class="mb-1 flex justify-between gap-2">
                    <span x-text="item.quantity + ' × ' + item.name"></span>
                    <span x-text="money(lineTotal(item))"></span>
                </div>
            </template>
            <hr class="my-3">
            <div class="flex justify-between font-bold"><span>Total</span><span x-text="money(completedOrders?.[0]?.total ?? totals.total)"></span></div>
            <p class="mt-4 text-center text-xs">Thank you!</p>
        </div>
    </div>

    {{-- Customer modal --}}
    <div x-show="showCustomerModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.outside="showCustomerModal = false">
            <div class="flex items-center justify-between border-b px-5 py-3.5">
                <h3 class="text-base font-bold">Select Customer</h3>
                <button type="button" @click="showCustomerModal = false" class="text-xl text-slate-400">&times;</button>
            </div>
            <div class="p-4">
                <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()"
                       placeholder="Search name, email, phone…"
                       class="mb-3 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100">
                <button type="button" @click="customer = null; showCustomerModal = false"
                        class="mb-2 w-full rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-left text-sm font-semibold text-blue-700">
                    Walk-in Customer
                </button>
                <div class="pos-scroll max-h-64 space-y-0.5 overflow-y-auto">
                    <template x-for="c in customers" :key="c.id">
                        <button type="button" @click="customer = c; showCustomerModal = false"
                                class="w-full rounded-lg px-3 py-2.5 text-left hover:bg-slate-50">
                            <div class="font-medium" x-text="c.name"></div>
                            <div class="text-xs text-slate-400" x-text="[c.email, c.phone].filter(Boolean).join(' · ')"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Discount modal --}}
    <div x-show="showDiscountModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl" @click.outside="showDiscountModal = false">
            <h3 class="mb-4 text-base font-bold">Invoice Discount</h3>
            <label class="mb-1 block text-sm text-slate-500">Percent (%)</label>
            <input type="number" min="0" max="100" step="0.01" x-model.number="invoiceDiscountPercent"
                   class="mb-3 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100">
            <label class="mb-1 block text-sm text-slate-500">Fixed amount</label>
            <input type="number" min="0" step="0.01" x-model.number="invoiceDiscountFixed"
                   class="mb-4 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100">
            <div class="flex gap-2">
                <button type="button" @click="invoiceDiscountPercent = 0; invoiceDiscountFixed = 0; showDiscountModal = false"
                        class="flex-1 rounded-lg border border-slate-200 py-2.5 text-sm font-semibold">Clear</button>
                <button type="button" @click="showDiscountModal = false"
                        class="flex-1 rounded-lg bg-[#2563eb] py-2.5 text-sm font-semibold text-white">Apply</button>
            </div>
        </div>
    </div>

    {{-- Note modal --}}
    <div x-show="showNoteModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl" @click.outside="showNoteModal = false">
            <h3 class="mb-3 text-base font-bold">Sale Note</h3>
            <textarea x-model="notes" rows="4" class="mb-4 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100" placeholder="Optional note…"></textarea>
            <button type="button" @click="showNoteModal = false" class="w-full rounded-lg bg-[#2563eb] py-2.5 text-sm font-semibold text-white">Save</button>
        </div>
    </div>

    {{-- Variant picker --}}
    <div x-show="variantProduct" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" @click.outside="variantProduct = null">
            <h3 class="mb-1 text-base font-bold" x-text="variantProduct?.name"></h3>
            <p class="mb-4 text-sm text-slate-500">Select a variant</p>
            <div class="space-y-2">
                <template x-for="v in (variantProduct?.variants || [])" :key="v.id">
                    <button type="button" @click="addVariant(variantProduct, v); variantProduct = null" :disabled="v.stock < 1"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-left hover:border-blue-400 disabled:opacity-40">
                        <div>
                            <div class="font-semibold" x-text="v.name"></div>
                            <div class="text-xs text-slate-400" x-text="'SKU: ' + v.sku"></div>
                            <div class="font-mono text-xs font-semibold text-blue-700" x-text="'Barcode: ' + (v.barcode || v.sku)"></div>
                            <div class="text-xs text-slate-400" x-text="'Stock ' + v.stock"></div>
                        </div>
                        <div class="font-bold text-[#2563eb]" x-text="money(v.price)"></div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Pay modal --}}
    <div x-show="showPayModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" @click.outside="!paying && (showPayModal = false)">
            <h3 class="mb-4 text-base font-bold">Complete Payment</h3>
            <div class="mb-4 rounded-lg bg-blue-50 px-4 py-3 text-center">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-500">Amount due</div>
                <div class="text-3xl font-bold text-[#1d4ed8]" x-text="money(totals.total)"></div>
            </div>
            <label class="mb-1.5 block text-sm text-slate-500">Payment method</label>
            <div class="mb-4 grid grid-cols-2 gap-2">
                <template x-for="m in paymentMethods" :key="m.value">
                    <button type="button" @click="paymentMethod = m.value"
                            class="rounded-lg border px-3 py-2.5 text-sm font-semibold"
                            :class="paymentMethod === m.value ? 'border-[#2563eb] bg-blue-50 text-[#1d4ed8]' : 'border-slate-200'"
                            x-text="m.label"></button>
                </template>
            </div>
            <label class="mb-1.5 block text-sm text-slate-500">Amount received</label>
            <input type="number" min="0" step="0.01" x-model.number="amountPaid"
                   class="mb-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-lg font-semibold outline-none focus:ring-2 focus:ring-blue-100">
            <div class="mb-4 flex justify-between text-sm">
                <span class="text-slate-500">Change</span>
                <span class="font-bold text-emerald-600" x-text="money(Math.max(0, (amountPaid || 0) - totals.total))"></span>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="showPayModal = false" :disabled="paying" class="flex-1 rounded-lg border border-slate-200 py-3 text-sm font-semibold">Cancel</button>
                <button type="button" @click="completeSale()" :disabled="paying || (amountPaid || 0) < totals.total"
                        class="flex-1 rounded-lg bg-[#2563eb] py-3 text-sm font-bold text-white disabled:opacity-50">
                    <span x-text="paying ? 'Processing…' : 'Confirm (F10)'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Success --}}
    <div x-show="completedOrders" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-xl font-bold">Sale Completed</h3>
            <p class="mt-1 text-sm text-slate-500">Order created and stock updated.</p>
            <div class="mt-4 space-y-2">
                <template x-for="o in (completedOrders || [])" :key="o.id">
                    <a :href="o.url" class="block rounded-lg border border-slate-200 px-4 py-3 text-left hover:border-blue-300">
                        <div class="font-mono font-bold" x-text="o.order_number"></div>
                        <div class="text-sm text-[#2563eb]" x-text="money(o.total)"></div>
                    </a>
                </template>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="printReceipt()" class="rounded-lg border border-slate-200 py-3 text-sm font-semibold">Print (Ctrl+P)</button>
                <button type="button" @click="resetAfterSale()" class="rounded-lg bg-[#2563eb] py-3 text-sm font-bold text-white">New Sale</button>
            </div>
        </div>
    </div>

    {{-- Held sales --}}
    <div x-show="showHeldModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl" @click.outside="showHeldModal = false">
            <div class="flex items-center justify-between border-b px-5 py-3.5">
                <h3 class="text-base font-bold">Held Sales</h3>
                <button type="button" @click="showHeldModal = false" class="text-xl text-slate-400">&times;</button>
            </div>
            <div class="pos-scroll max-h-96 space-y-2 overflow-y-auto p-4">
                <template x-if="heldSales.length === 0">
                    <p class="py-8 text-center text-sm text-slate-400">No held sales</p>
                </template>
                <template x-for="h in heldSales" :key="h.id">
                    <div class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3">
                        <div class="flex-1">
                            <div class="font-semibold" x-text="h.label"></div>
                            <div class="text-xs text-slate-400" x-text="h.item_count + ' items · ' + formatHeldAt(h.held_at)"></div>
                        </div>
                        <button type="button" @click="resumeHeld(h)" class="rounded-md bg-[#2563eb] px-3 py-1.5 text-sm font-semibold text-white">Resume</button>
                        <button type="button" @click="deleteHeld(h.id)" class="rounded-md border border-rose-200 px-3 py-1.5 text-sm font-semibold text-rose-600">Delete</button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
function posApp(config) {
    return {
        ...config,
        search: '',
        categoryId: null,
        popular: false,
        showMoreCats: false,
        products: [],
        meta: { current_page: 1, last_page: 1, per_page: 35, total: 0 },
        page: 1,
        loading: false,
        syncing: false,
        scanning: false,
        viewMode: 'grid',
        cart: [],
        selectedIndex: -1,
        customer: null,
        customers: [],
        customerSearch: '',
        notes: '',
        invoiceDiscountPercent: 0,
        invoiceDiscountFixed: 0,
        saleNumber: 'TEMP-' + Math.floor(100000 + Math.random() * 900000),
        clock: '',
        toast: '',
        toastTimer: null,
        showCustomerModal: false,
        showDiscountModal: false,
        showNoteModal: false,
        showPayModal: false,
        showHeldModal: false,
        variantProduct: null,
        paymentMethod: 'cash',
        paymentMethods: [
            { value: 'cash', label: 'Cash' },
            { value: 'bkash', label: 'bKash' },
            { value: 'nagad', label: 'Nagad' },
            { value: 'cod', label: 'Other' },
        ],
        amountPaid: 0,
        paying: false,
        completedOrders: null,
        lastReceiptCart: [],
        heldSales: [],
        heldCount: 0,

        get visibleCategories() {
            return (this.categories || []).slice(0, 6);
        },
        get moreCategories() {
            return (this.categories || []).slice(6);
        },
        get totalQty() {
            return this.cart.reduce((s, i) => s + i.quantity, 0);
        },
        get totals() {
            const subtotal = this.cart.reduce((s, i) => s + this.lineTotal(i), 0);
            const itemDiscount = this.cart.reduce((s, i) => s + (Number(i.discount) || 0), 0);
            let invoiceDiscount = 0;
            if (this.invoiceDiscountPercent > 0) {
                invoiceDiscount = Math.round(subtotal * (this.invoiceDiscountPercent / 100) * 100) / 100;
            } else {
                invoiceDiscount = Math.min(this.invoiceDiscountFixed || 0, subtotal);
            }
            const taxable = Math.max(0, subtotal - invoiceDiscount);
            const tax = Math.round(taxable * (this.taxRate / 100) * 100) / 100;
            const before = taxable + tax;
            const total = Math.round(before);
            const rounding = Math.round((total - before) * 100) / 100;
            return {
                subtotal: Math.round(subtotal * 100) / 100,
                itemDiscount: Math.round(itemDiscount * 100) / 100,
                invoiceDiscount,
                tax,
                rounding,
                total,
            };
        },
        get pageButtons() {
            const last = this.meta.last_page || 1;
            const cur = this.meta.current_page || 1;
            const pages = [];
            const start = Math.max(1, cur - 2);
            const end = Math.min(last, start + 4);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },

        init() {
            this.tickClock();
            setInterval(() => this.tickClock(), 1000);
            this.fetchProducts();
            this.loadHeld(false);
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        tickClock() {
            this.clock = new Date().toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit',
            });
        },

        money(n) {
            return this.currencySymbol + Number(n || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
        },

        lineTotal(item) {
            return Math.max(0, (item.unit_price * item.quantity) - (Number(item.discount) || 0));
        },

        isInCart(product) {
            const ids = (product.variants || []).map(v => v.id);
            if (product.variant_id) ids.push(product.variant_id);
            return this.cart.some(i => ids.includes(i.variant_id));
        },

        setCategory(id) {
            if (id === 'popular') {
                this.popular = true;
                this.categoryId = null;
            } else {
                this.popular = false;
                this.categoryId = id;
            }
            this.page = 1;
            this.fetchProducts();
        },

        async syncProducts() {
            this.syncing = true;
            try {
                await this.fetchProducts();
                this.showToast('Products synced');
            } finally {
                this.syncing = false;
            }
        },

        async fetchProducts() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    page: this.page,
                    per_page: 35,
                    search: this.search || '',
                });
                if (this.popular) params.set('popular', '1');
                if (this.categoryId) params.set('category_id', this.categoryId);

                const res = await fetch(this.routes.products + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json();
                this.products = json.data || [];
                this.meta = json.meta || this.meta;
                this.$nextTick(() => this.paintAllBarcodes());
            } catch (e) {
                this.showToast('Failed to load products');
            } finally {
                this.loading = false;
            }
        },

        paintAllBarcodes() {
            document.querySelectorAll('svg.pos-barcode[data-barcode]').forEach((el) => {
                this.paintBarcode(el, el.getAttribute('data-barcode'));
            });
        },

        paintBarcode(el, value) {
            if (!el || !value || typeof JsBarcode === 'undefined') return;
            try {
                JsBarcode(el, String(value), {
                    format: 'CODE128',
                    width: 1.1,
                    height: 28,
                    displayValue: true,
                    fontSize: 9,
                    margin: 1,
                    background: '#ffffff',
                    lineColor: '#0f172a',
                });
            } catch (e) {
                // invalid characters for CODE128 — show plain text
                el.outerHTML = '<div class="px-1 py-0.5 text-center font-mono text-[9px] font-semibold text-slate-700">' + value + '</div>';
            }
        },

        onSearchEnter() {
            const q = (this.search || '').trim();
            if (!q) return;
            this.scanAndAdd(q);
        },

        async scanAndAdd(code) {
            const q = (code || '').trim();
            if (!q || this.scanning) return;
            this.scanning = true;
            try {
                const params = new URLSearchParams({ code: q });
                const res = await fetch(this.routes.scan + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Not found');

                this.addVariant(json.product, json.variant);
                this.search = '';
                const bc = json.variant.barcode || json.variant.sku || q;
                this.showToast('Scanned ' + bc + ' → ' + json.product.name);
                this.$nextTick(() => this.$refs.searchInput?.focus());
            } catch (e) {
                this.showToast(e.message || 'Barcode not found');
                // Keep search text so cashier can fix / search by name
                this.page = 1;
                this.fetchProducts();
            } finally {
                this.scanning = false;
            }
        },

        goPage(p) {
            if (p < 1 || p > this.meta.last_page) return;
            this.page = p;
            this.fetchProducts();
        },

        addProduct(product) {
            if (product.has_multiple_variants && (product.variants || []).length > 1) {
                this.variantProduct = product;
                return;
            }
            const v = (product.variants || [])[0] || {
                id: product.variant_id,
                name: 'Default',
                sku: product.sku,
                price: product.price,
                stock: product.stock,
            };
            this.addVariant(product, v);
        },

        addVariant(product, v) {
            if (!v?.id || v.stock < 1) {
                this.showToast('Out of stock');
                return;
            }
            const idx = this.cart.findIndex(i => i.variant_id === v.id);
            if (idx >= 0) {
                if (this.cart[idx].quantity >= v.stock) {
                    this.showToast('Not enough stock');
                    return;
                }
                this.cart[idx].quantity++;
                this.selectedIndex = idx;
            } else {
                this.cart.push({
                    variant_id: v.id,
                    product_id: product.id,
                    name: product.name + (v.name && v.name !== 'Default' ? ' — ' + v.name : ''),
                    sku: v.sku || product.sku,
                    barcode: v.barcode || product.barcode || null,
                    unit_price: Number(v.price),
                    discount: 0,
                    quantity: 1,
                    stock: v.stock,
                });
                this.selectedIndex = this.cart.length - 1;
            }
        },

        changeQty(index, delta) {
            const item = this.cart[index];
            if (!item) return;
            const next = item.quantity + delta;
            if (next < 1) {
                this.removeItem(index);
                return;
            }
            if (next > item.stock) {
                this.showToast('Not enough stock');
                return;
            }
            item.quantity = next;
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            if (this.selectedIndex >= this.cart.length) this.selectedIndex = this.cart.length - 1;
        },

        clearCart() {
            if (this.cart.length && !confirm('Clear the current cart?')) return;
            this.cart = [];
            this.selectedIndex = -1;
            this.invoiceDiscountPercent = 0;
            this.invoiceDiscountFixed = 0;
            this.notes = '';
            this.saleNumber = 'TEMP-' + Math.floor(100000 + Math.random() * 900000);
        },

        openCustomer() {
            this.showCustomerModal = true;
            this.searchCustomers();
        },

        async searchCustomers() {
            const params = new URLSearchParams({ search: this.customerSearch || '' });
            const res = await fetch(this.routes.customers + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            this.customers = json.data || [];
        },

        openPay() {
            if (!this.cart.length) return;
            this.amountPaid = this.totals.total;
            this.paymentMethod = 'cash';
            this.showPayModal = true;
        },

        async completeSale() {
            if (this.paying || !this.cart.length) return;
            if ((this.amountPaid || 0) < this.totals.total) {
                this.showToast('Amount received is less than total');
                return;
            }
            this.paying = true;
            this.lastReceiptCart = JSON.parse(JSON.stringify(this.cart));
            try {
                const res = await fetch(this.routes.checkout, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({
                            variant_id: i.variant_id,
                            quantity: i.quantity,
                            unit_price: i.unit_price,
                            discount: i.discount || 0,
                        })),
                        customer_id: this.customer?.id || null,
                        invoice_discount: this.invoiceDiscountPercent > 0 ? 0 : (this.invoiceDiscountFixed || 0),
                        invoice_discount_percent: this.invoiceDiscountPercent || 0,
                        notes: this.notes || null,
                        payment_method: this.paymentMethod,
                        amount_paid: this.amountPaid,
                    }),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Checkout failed');
                this.showPayModal = false;
                this.completedOrders = json.orders;
                this.fetchProducts();
            } catch (e) {
                this.showToast(e.message || 'Checkout failed');
            } finally {
                this.paying = false;
            }
        },

        printReceipt() {
            window.print();
        },

        resetAfterSale() {
            this.completedOrders = null;
            this.cart = [];
            this.lastReceiptCart = [];
            this.customer = null;
            this.notes = '';
            this.invoiceDiscountPercent = 0;
            this.invoiceDiscountFixed = 0;
            this.selectedIndex = -1;
            this.saleNumber = 'TEMP-' + Math.floor(100000 + Math.random() * 900000);
            this.$refs.searchInput?.focus();
        },

        async holdSale() {
            if (!this.cart.length) return;
            try {
                const res = await fetch(this.routes.hold, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        cart: this.cart,
                        customer: this.customer,
                        notes: this.notes,
                        invoice_discount: this.invoiceDiscountFixed,
                        invoice_discount_percent: this.invoiceDiscountPercent,
                    }),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Hold failed');
                this.heldCount = json.held_count;
                this.cart = [];
                this.customer = null;
                this.notes = '';
                this.invoiceDiscountPercent = 0;
                this.invoiceDiscountFixed = 0;
                this.saleNumber = 'TEMP-' + Math.floor(100000 + Math.random() * 900000);
                this.showToast('Sale held');
            } catch (e) {
                this.showToast(e.message || 'Hold failed');
            }
        },

        async loadHeld(open = true) {
            const res = await fetch(this.routes.held, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            this.heldSales = json.data || [];
            this.heldCount = this.heldSales.length;
            if (open) this.showHeldModal = true;
        },

        resumeHeld(h) {
            if (this.cart.length && !confirm('Replace current cart with held sale?')) return;
            this.cart = h.cart || [];
            this.customer = h.customer || null;
            this.notes = h.notes || '';
            this.invoiceDiscountFixed = h.invoice_discount || 0;
            this.invoiceDiscountPercent = h.invoice_discount_percent || 0;
            this.showHeldModal = false;
            this.deleteHeld(h.id, false);
            this.showToast('Held sale resumed');
        },

        async deleteHeld(id, refresh = true) {
            await fetch(this.routes.deleteHeld + '/' + id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (refresh) this.loadHeld(true);
            else this.loadHeld(false);
        },

        formatHeldAt(iso) {
            try { return new Date(iso).toLocaleString(); } catch { return iso; }
        },

        showToast(msg) {
            this.toast = msg;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast = '', 2500);
        },

        onKeydown(e) {
            const tag = (e.target.tagName || '').toLowerCase();
            const typing = tag === 'input' || tag === 'textarea';

            if (e.key === 'F2') {
                e.preventDefault();
                this.$refs.searchInput?.focus();
                this.$refs.searchInput?.select();
                return;
            }
            if (e.key === 'F4') {
                e.preventDefault();
                this.openCustomer();
                return;
            }
            if (e.key === 'F6') {
                e.preventDefault();
                if (this.cart.length) this.showDiscountModal = true;
                return;
            }
            if (e.key === 'F7') {
                e.preventDefault();
                this.clearCart();
                return;
            }
            if (e.key === 'F8') {
                e.preventDefault();
                this.holdSale();
                return;
            }
            if (e.key === 'F9') {
                e.preventDefault();
                this.openPay();
                return;
            }
            if (e.key === 'F10') {
                e.preventDefault();
                if (this.showPayModal) this.completeSale();
                else if (this.cart.length) this.openPay();
                return;
            }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
                if (this.completedOrders) {
                    e.preventDefault();
                    this.printReceipt();
                }
                return;
            }
            if (e.key === 'Escape') {
                this.showCustomerModal = false;
                this.showDiscountModal = false;
                this.showNoteModal = false;
                this.showPayModal = false;
                this.showHeldModal = false;
                this.showMoreCats = false;
                this.variantProduct = null;
                return;
            }
            if (!typing && (e.key === 'Delete') && this.selectedIndex >= 0) {
                e.preventDefault();
                this.removeItem(this.selectedIndex);
            }
        },
    };
}
</script>
@endpush
