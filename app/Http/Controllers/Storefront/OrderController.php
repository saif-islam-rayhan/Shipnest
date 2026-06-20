<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['shop', 'items'])
            ->forUser($request->user()->id)
            ->latest()
            ->paginate(10);

        return view('storefront.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load(['shop', 'items.product.images', 'payment', 'shippingAddress']);

        return view('storefront.orders.show', compact('order'));
    }

    public function success(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with(['items', 'shippingAddress'])
            ->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $estimatedDelivery = $this->orderService->getEstimatedDelivery($order);

        return view('storefront.orders.success', compact('order', 'estimatedDelivery'));
    }

    public function paymentCallback(Request $request, string $gateway): RedirectResponse
    {
        $result = $this->paymentService->handleCallback($gateway, $request->all());

        if ($result['success']) {
            return redirect($result['redirect_url'])
                ->with('success', 'Payment completed successfully.');
        }

        return redirect($result['redirect_url'])
            ->with('error', 'Payment failed or was cancelled.');
    }
}
