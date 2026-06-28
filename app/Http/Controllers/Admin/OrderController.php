<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['user', 'shop', 'items'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('payment_method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->input('merchant_id'), fn ($q, $id) => $q->forShop($id))
            ->when($request->input('search'), fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $merchants = Merchant::query()->orderBy('shop_name')->get(['id', 'shop_name']);

        return view('admin.orders.index', compact('orders', 'merchants'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'shop', 'items.product', 'shippingAddress', 'payment', 'returns']);
        $disputes = OrderDispute::query()->where('order_id', $order->id)->with(['user', 'merchant'])->get();

        return view('admin.orders.show', compact('order', 'disputes'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['status' => ['required', 'string']]);
        $this->orderService->updateStatus($order, OrderStatus::from($request->input('status')));

        return back()->with('success', 'Order status updated.');
    }

    public function storeDispute(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'merchant_id' => ['nullable', 'integer'],
        ]);

        OrderDispute::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'merchant_id' => $data['merchant_id'] ?? null,
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
        ]);

        return back()->with('success', 'Dispute recorded.');
    }

    public function resolveDispute(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:resolved,closed'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $dispute->update($data);

        return back()->with('success', 'Dispute updated.');
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$order->total],
            'method' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $refund = $this->paymentService->processRefund(
            $order,
            (float) $data['amount'],
            auth()->user(),
            $data['note'] ?? null,
        );

        $message = $refund->status === 'completed'
            ? 'Refund processed.'
            : 'Refund recorded but gateway refund failed — check notes.';

        return back()->with($refund->status === 'completed' ? 'success' : 'error', $message);
    }
}
