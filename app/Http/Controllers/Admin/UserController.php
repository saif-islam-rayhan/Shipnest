<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['merchant', 'roles'])
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->input('role'), fn ($q, $role) => $q->role($role))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roles = Role::query()->pluck('name');

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function show(User $user): View
    {
        $user->load(['merchant', 'roles', 'orders', 'addresses']);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $user->load('roles');
        $roles = Role::query()->pluck('name');

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        if (! empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot delete admin users.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function impersonate(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot impersonate admin users.');
        }

        session(['impersonator_id' => auth()->id()]);
        Auth::login($user);

        return redirect()->route('home')->with('success', 'Now impersonating '.$user->name);
    }

    public function stopImpersonating(): RedirectResponse
    {
        $adminId = session('impersonator_id');

        if (! $adminId) {
            return redirect()->route('home');
        }

        session()->forget('impersonator_id');
        Auth::loginUsingId($adminId);

        return redirect()->route('admin.dashboard')->with('success', 'Stopped impersonating.');
    }
}
