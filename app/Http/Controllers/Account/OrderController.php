<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->input('status', 'all');

        $orders = Order::query()
            ->with(['shop'])
            ->withCount('items')
            ->forUser($request->user()->id)
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'returned') {
                    return $query->returned();
                }

                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $statuses = [
            'all' => 'All',
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
        ];

        return view('account.orders.index', compact('orders', 'status', 'statuses'));
    }

    public function show(Request $request, string $orderNumber): View
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->forUser($request->user()->id)
            ->with(['shop', 'items.product.images', 'payment', 'shippingAddress', 'statusHistories'])
            ->firstOrFail();

        $timelineSteps = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ];

        return view('account.orders.show', compact('order', 'timelineSteps'));
    }

    public function cancel(Request $request, string $orderNumber): RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->forUser($request->user()->id)
            ->firstOrFail();

        try {
            $this->orderService->cancelOrder($order);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('account.orders.show', $order->order_number)
            ->with('success', 'Order cancelled successfully.');
    }

    public function invoice(Request $request, string $orderNumber)
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->forUser($request->user()->id)
            ->firstOrFail();

        return $this->invoiceService->download($order);
    }
}
