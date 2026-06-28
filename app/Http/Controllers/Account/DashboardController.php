<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user->wallet()->firstOrCreate(['balance' => 0]);

        $stats = [
            'total_orders' => $user->orders()->count(),
            'wishlist_items' => $user->wishlists()->count(),
            'reviews_written' => $user->reviews()->count(),
            'wallet_balance' => (float) ($user->wallet?->balance ?? 0),
        ];

        $recentOrders = Order::query()
            ->with(['shop'])
            ->withCount('items')
            ->forUser($user->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('account.dashboard', compact('stats', 'recentOrders'));
    }
}
