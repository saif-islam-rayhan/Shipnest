<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Profile Settings</h1>

    <div class="space-y-8 max-w-2xl">
        {{-- Profile Info --}}
        <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Personal Information</h2>
            <form action="{{ route('account.profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PATCH')

                <div class="flex items-center gap-4">
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full ring-2 ring-primary">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Avatar</label>
                        <input type="file" name="avatar" accept="image/*" class="text-sm">
                        @error('avatar')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="input-field" required>
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input-field" required>
                    @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input-field">
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>

        {{-- Change Password --}}
        <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Change Password</h2>
            <form action="{{ route('account.profile.password') }}" method="POST" class="space-y-4">
                @csrf @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" class="input-field" required>
                    @error('current_password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" class="input-field" required>
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="input-field" required>
                </div>
                <button type="submit" class="btn-primary">Update Password</button>
            </form>
        </div>

        {{-- Notifications --}}
        <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Notification Preferences</h2>
            <form action="{{ route('account.profile.notifications') }}" method="POST" class="space-y-4">
                @csrf @method('PATCH')
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="notify_email" value="1" class="rounded text-primary focus:ring-primary"
                        @checked(old('notify_email', $user->notify_email ?? true))>
                    <span class="text-sm text-gray-700">Email notifications (order updates, promotions)</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="notify_sms" value="1" class="rounded text-primary focus:ring-primary"
                        @checked(old('notify_sms', $user->notify_sms ?? false))>
                    <span class="text-sm text-gray-700">SMS notifications (delivery updates)</span>
                </label>
                <button type="submit" class="btn-primary">Save Preferences</button>
            </form>
        </div>
    </div>
</x-layouts.account>
