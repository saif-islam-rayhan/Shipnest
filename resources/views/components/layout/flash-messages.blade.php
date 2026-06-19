@if(session('success') || session('error'))
<div class="max-w-7xl mx-auto px-4 mt-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
  @if(session('success'))
    <div class="rounded-md bg-green-50 p-4 border border-green-200">
      <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
    </div>
  @endif
  @if(session('error'))
    <div class="rounded-md bg-red-50 p-4 border border-red-200">
      <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
    </div>
  @endif
</div>
@endif
