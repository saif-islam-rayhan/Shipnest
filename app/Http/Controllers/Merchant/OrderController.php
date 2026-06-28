<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    use InteractsWithShop;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        $orders = Order::query()
            ->with(['user'])
            ->withCount(['items' => fn ($q) => $q->where('merchant_id', $shop->id)])
            ->forShop($shop->id)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('merchant.orders.index', compact('shop', 'orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $shop = $this->shop($request);
        $this->authorizeOrder($shop, $order);

        $order->load(['user', 'shippingAddress', 'payment', 'items' => fn ($q) => $q->where('merchant_id', $shop->id)->with('product.images')]);

        return view('merchant.orders.show', compact('shop', 'order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->shop($request);
        $this->authorizeOrder($shop, $order);

        $request->validate(['status' => ['required', 'string']]);
        $this->orderService->updateStatus($order, OrderStatus::from($request->input('status')));

        return back()->with('success', 'Order status updated.');
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->shop($request);
        $this->authorizeOrder($shop, $order);
        $this->orderService->updateStatus($order, OrderStatus::Confirmed);

        return back()->with('success', 'Order confirmed.');
    }

    public function readyForPickup(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->shop($request);
        $this->authorizeOrder($shop, $order);
        $this->orderService->updateStatus($order, OrderStatus::Processing);

        return back()->with('success', 'Order marked as ready for pickup.');
    }

    public function invoice(Request $request, Order $order)
    {
        $shop = $this->shop($request);
        $this->authorizeOrder($shop, $order);
        $order->setRelation('items', $order->items()->where('merchant_id', $shop->id)->get());

        return $this->invoiceService->download($order);
    }
}
