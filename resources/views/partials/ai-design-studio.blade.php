{{-- Shared AI Mode studio (admin + merchant). Expects: $generateUrl, $createProductUrl --}}
<div
    class="max-w-3xl mx-auto"
    x-data="aiDesignStudio({
        generateUrl: @js($generateUrl),
        createProductUrl: @js($createProductUrl),
    })"
>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden flex flex-col" style="min-height: 72vh;">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">AI Mode</h1>
                <p class="text-sm text-gray-500 mt-0.5">Design, search, analyze bestsellers, market potential & trends.</p>
            </div>
            <span class="text-xs font-medium text-emerald-700 bg-emerald-50 ring-1 ring-emerald-200 px-2.5 py-1 rounded-md whitespace-nowrap">Free</span>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4" x-ref="thread" style="max-height: calc(72vh - 220px);">
            <div x-show="messages.length === 0" class="rounded-lg bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-600">
                Pick a category below, then describe your need — e.g.
                <button type="button" class="text-[#F57C00] font-medium hover:underline" @click="fillExample('Design a ceramic Labubu mug with soft pastel colors')">Design Labubu mug</button>
                or
                <button type="button" class="text-[#F57C00] font-medium hover:underline" @click="mode = 'search'; fillExample('smart watch')">smart watch</button>
            </div>

            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div
                        class="max-w-[92%] rounded-2xl px-4 py-3 text-sm"
                        :class="msg.role === 'user'
                            ? 'bg-[#1A237E] text-white rounded-br-md'
                            : 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 rounded-bl-md'"
                    >
                        <p class="whitespace-pre-wrap" x-text="msg.text"></p>

                        <template x-if="msg.image_url">
                            <div class="mt-3 space-y-2">
                                <img :src="msg.image_url" alt="AI design" class="w-full rounded-lg ring-1 ring-black/5 bg-white object-cover" style="max-height: 420px;">
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        :href="createProductLink(msg.image_url)"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold bg-[#F57C00] text-white px-3 py-1.5 rounded-lg hover:bg-orange-600"
                                    >Use on new product</a>
                                    <a
                                        :href="msg.image_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium bg-white text-gray-700 px-3 py-1.5 rounded-lg ring-1 ring-gray-200 hover:bg-gray-50"
                                    >Open image</a>
                                </div>
                            </div>
                        </template>

                        <template x-if="msg.products && msg.products.length">
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(p, pi) in msg.products" :key="pi">
                                    <div class="flex gap-2 rounded-lg bg-white ring-1 ring-gray-200 p-2">
                                        <template x-if="p.image">
                                            <img :src="p.image" alt="" class="w-14 h-14 rounded object-cover bg-gray-100 shrink-0">
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-gray-900 line-clamp-2" x-text="p.name"></p>
                                            <p class="text-xs text-[#F57C00] mt-0.5" x-show="p.price_label" x-text="p.price_label"></p>
                                            <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-2" x-show="p.meta" x-text="p.meta"></p>
                                            <a
                                                x-show="p.url"
                                                :href="p.url"
                                                target="_blank"
                                                class="inline-block text-[11px] font-medium text-[#1A237E] hover:underline mt-1"
                                            >View</a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="loading" class="flex justify-start" x-cloak>
                <div class="bg-gray-50 ring-1 ring-gray-200 rounded-2xl rounded-bl-md px-4 py-3 text-sm text-gray-500" x-text="loadingLabel()"></div>
            </div>
        </div>

        <div class="border-t border-gray-100 p-4 space-y-3">
            <p x-show="error" x-text="error" class="text-sm text-red-600" x-cloak></p>

            <form @submit.prevent="send()" class="relative">
                <div
                    class="rounded-2xl bg-white border border-orange-200/80 shadow-[0_0_0_3px_rgba(245,124,0,0.08)] focus-within:border-[#F57C00] focus-within:shadow-[0_0_0_3px_rgba(245,124,0,0.15)] transition"
                >
                    <textarea
                        x-model="prompt"
                        rows="3"
                        class="w-full resize-none border-0 bg-transparent px-4 pt-3 pb-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-0 focus:outline-none"
                        :placeholder="placeholder()"
                        :disabled="loading"
                        @keydown.enter.prevent="if (!$event.shiftKey) send()"
                    ></textarea>
                    <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400" title="Attachments coming soon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </span>
                        <button
                            type="submit"
                            class="pointer-events-auto inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#F57C00] text-white hover:bg-orange-600 disabled:opacity-40 shadow-sm"
                            :disabled="loading || !canSend()"
                            aria-label="Send"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </form>

            <div class="flex items-center gap-2 overflow-x-auto pb-1" style="scrollbar-width: thin;">
                <template x-for="chip in modes" :key="chip.id">
                    <button
                        type="button"
                        @click="selectMode(chip.id)"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition whitespace-nowrap"
                        :class="mode === chip.id
                            ? 'border-[#F57C00] bg-orange-50 text-[#E65100]'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                    >
                        <span x-show="chip.id === 'design'" aria-hidden="true">✦</span>
                        <span x-text="chip.label"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
