<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Manage Shops</h1>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Shop</th>
            <th class="px-4 py-3 text-left">Owner</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Products</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($shops as $shop)
            <tr>
              <td class="px-4 py-3 font-medium">{{ $shop->name }}</td>
              <td class="px-4 py-3">{{ $shop->owner->name }}</td>
              @php $status = \App\Enums\ShopStatus::from($shop->status) @endphp
              <td class="px-4 py-3"><span class="badge bg-{{ $status->color() }}-100 text-{{ $status->color() }}-800">{{ $status->label() }}</span></td>
              <td class="px-4 py-3">{{ $shop->products_count }}</td>
              <td class="px-4 py-3 text-right space-x-2">
                @if($status === \App\Enums\ShopStatus::Pending)
                  <form action="{{ route('admin.shops.approve', $shop) }}" method="POST" class="inline">@csrf @method('PATCH')<button type="submit" class="text-green-600 hover:underline text-xs">Approve</button></form>
                @endif
                @if($status === \App\Enums\ShopStatus::Active)
                  <form action="{{ route('admin.shops.suspend', $shop) }}" method="POST" class="inline">@csrf @method('PATCH')<button type="submit" class="text-red-600 hover:underline text-xs">Suspend</button></form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $shops->links() }}</div>
  </div>
</x-layouts.app>
