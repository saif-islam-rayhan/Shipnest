<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
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

        $order->load(['shop', 'items.product', 'payment', 'address']);

        return view('storefront.orders.show', compact('order'));
    }

    public function paymentCallback(Request $request, string $gateway): RedirectResponse
    {
        $method = PaymentMethod::from($gateway);
        $payment = $this->paymentService->verify($method, $request->all());

        if ($payment->status->value === 'completed') {
            return redirect()
                ->route('orders.show', $payment->order)
                ->with('success', 'Payment completed successfully.');
        }

        return redirect()
            ->route('orders.show', $payment->order)
            ->with('error', 'Payment failed or was cancelled.');
    }
}
