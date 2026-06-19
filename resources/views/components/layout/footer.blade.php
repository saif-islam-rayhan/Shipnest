<footer class="bg-secondary text-white mt-12">
  <div class="max-w-7xl mx-auto px-4 py-10">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
      <div>
        <h3 class="text-lg font-bold mb-4"><span class="text-primary">Ship</span>Nest</h3>
        <p class="text-sm text-gray-300">Bangladesh's leading multi-vendor marketplace. Shop from thousands of sellers nationwide.</p>
      </div>
      <div>
        <h4 class="font-semibold mb-3">Customer Care</h4>
        <ul class="space-y-2 text-sm text-gray-300">
          <li><a href="#" class="hover:text-primary">Help Center</a></li>
          <li><a href="#" class="hover:text-primary">How to Buy</a></li>
          <li><a href="#" class="hover:text-primary">Returns & Refunds</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold mb-3">Sell on ShipNest</h4>
        <ul class="space-y-2 text-sm text-gray-300">
          <li><a href="{{ route('register') }}" class="hover:text-primary">Become a Seller</a></li>
          <li><a href="#" class="hover:text-primary">Seller Center</a></li>
          <li><a href="#" class="hover:text-primary">Seller Policies</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold mb-3">Contact</h4>
        <ul class="space-y-2 text-sm text-gray-300">
          <li>{{ config('shipnest.support_phone') }}</li>
          <li>{{ config('shipnest.support_email') }}</li>
        </ul>
      </div>
    </div>
    <div class="border-t border-secondary-700 mt-8 pt-6 text-center text-sm text-gray-400">
      &copy; {{ date('Y') }} {{ config('shipnest.name') }}. All rights reserved.
    </div>
  </div>
</footer>
