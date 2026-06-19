<footer class="bg-secondary text-white mt-12">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div>
                <h3 class="text-lg font-bold mb-4"><span class="text-primary">Ship</span>Nest</h3>
                <p class="text-sm text-gray-300 leading-relaxed">
                    Bangladesh's leading multi-vendor marketplace. Shop from thousands of trusted sellers nationwide with secure payments and fast delivery.
                </p>
                <div class="flex items-center gap-3 mt-4">
                    <a href="{{ config('shipnest.social.facebook') }}" target="_blank" rel="noopener" class="w-8 h-8 rounded-full bg-secondary-700 flex items-center justify-center hover:bg-primary transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="{{ config('shipnest.social.instagram') }}" target="_blank" rel="noopener" class="w-8 h-8 rounded-full bg-secondary-700 flex items-center justify-center hover:bg-primary transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="{{ config('shipnest.social.youtube') }}" target="_blank" rel="noopener" class="w-8 h-8 rounded-full bg-secondary-700 flex items-center justify-center hover:bg-primary transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="font-semibold mb-4 text-white">Customer Service</h4>
                <ul class="space-y-2.5 text-sm text-gray-300">
                    <li><a href="#" class="hover:text-primary transition-colors">Help Center</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">How to Buy</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Returns & Refunds</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Track My Order</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Shipping Policy</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-semibold mb-4 text-white">Merchant</h4>
                <ul class="space-y-2.5 text-sm text-gray-300">
                    <li><a href="{{ route('register', ['role' => 'merchant']) }}" class="hover:text-primary transition-colors">Become a Seller</a></li>
                    <li><a href="{{ route('merchant.dashboard') }}" class="hover:text-primary transition-colors">Seller Center</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Seller Policies</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Merchant Guidelines</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-semibold mb-4 text-white">Contact</h4>
                <ul class="space-y-2.5 text-sm text-gray-300">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ config('shipnest.support_phone') }}
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ config('shipnest.support_email') }}
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-secondary-700 mt-10 pt-8">
            <p class="text-sm text-gray-400 text-center mb-4">We Accept</p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="px-4 py-2 bg-white rounded text-xs font-bold text-gray-800">SSLCommerz</span>
                <span class="px-4 py-2 bg-pink-600 rounded text-xs font-bold text-white">bKash</span>
                <span class="px-4 py-2 bg-orange-500 rounded text-xs font-bold text-white">Nagad</span>
                <span class="px-4 py-2 bg-blue-800 rounded text-xs font-bold text-white italic">VISA</span>
                <span class="px-4 py-2 bg-red-600 rounded text-xs font-bold text-white">Mastercard</span>
            </div>
        </div>

        <div class="border-t border-secondary-700 mt-8 pt-6 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} {{ config('shipnest.name') }}. All rights reserved.
        </div>
    </div>
</footer>
