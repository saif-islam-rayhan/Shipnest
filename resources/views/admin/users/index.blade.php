<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Users</h1>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Email</th>
            <th class="px-4 py-3 text-left">Phone</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3 text-left">Shop</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($users as $user)
            <tr>
              <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
              <td class="px-4 py-3">{{ $user->email }}</td>
              <td class="px-4 py-3">{{ $user->phone }}</td>
              <td class="px-4 py-3"><span class="badge bg-gray-100 text-gray-800">{{ $user->role->label() }}</span></td>
              <td class="px-4 py-3">{{ $user->shop?->name ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
  </div>
</x-layouts.app>
