<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReturnRequest;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $returns = OrderReturn::query()
            ->with(['order', 'orderItem'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        $eligibleItems = OrderItem::query()
            ->with(['order', 'product.images'])
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', OrderStatus::Delivered->value))
            ->whereDoesntHave('returns')
            ->get()
            ->filter(fn (OrderItem $item) => $item->order->canRequestReturn());

        return view('account.returns.index', compact('returns', 'eligibleItems'));
    }

    public function store(StoreReturnRequest $request): RedirectResponse
    {
        $item = OrderItem::query()
            ->with('order')
            ->findOrFail($request->validated('order_item_id'));

        if ($item->order->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $item->order->canRequestReturn()) {
            return back()->with('error', 'This item is not eligible for return.');
        }

        if ($item->returns()->exists()) {
            return back()->with('error', 'A return request already exists for this item.');
        }

        OrderReturn::query()->create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'user_id' => $request->user()->id,
            'reason' => $request->validated('reason'),
            'description' => $request->validated('description'),
            'status' => 'pending',
            'refund_amount' => $item->total,
        ]);

        return redirect()
            ->route('account.returns.index')
            ->with('success', 'Return request submitted successfully.');
    }
}
