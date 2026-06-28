<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Models\OrderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    use InteractsWithShop;

    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        $returns = OrderReturn::query()
            ->with(['order', 'orderItem', 'user'])
            ->whereHas('orderItem', fn ($q) => $q->where('merchant_id', $shop->id))
            ->latest()
            ->paginate(20);

        return view('merchant.returns.index', compact('shop', 'returns'));
    }

    public function approve(Request $request, OrderReturn $return): RedirectResponse
    {
        $shop = $this->shop($request);
        if ($return->orderItem->merchant_id !== $shop->id) {
            abort(403);
        }

        $request->validate(['merchant_note' => ['nullable', 'string', 'max:1000']]);
        $return->update(['status' => 'approved', 'merchant_note' => $request->input('merchant_note')]);

        return back()->with('success', 'Return approved.');
    }

    public function reject(Request $request, OrderReturn $return): RedirectResponse
    {
        $shop = $this->shop($request);
        if ($return->orderItem->merchant_id !== $shop->id) {
            abort(403);
        }

        $request->validate(['merchant_note' => ['required', 'string', 'max:1000']]);
        $return->update(['status' => 'rejected', 'merchant_note' => $request->input('merchant_note')]);

        return back()->with('success', 'Return rejected.');
    }
}
