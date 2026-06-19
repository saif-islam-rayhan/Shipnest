<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Models\Address;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->getCart($request->user());

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $groupedItems = $this->cartService->getItemsGroupedByShop($cart);
        $addresses = $request->user()->addresses()->latest()->get();
        $paymentMethods = PaymentMethod::cases();

        return view('storefront.checkout.index', compact('cart', 'groupedItems', 'addresses', 'paymentMethods'));
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $address = Address::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->validated('address_id'));

        $paymentMethod = PaymentMethod::from($request->validated('payment_method'));

        try {
            $orders = $this->orderService->createFromCart(
                user: $request->user(),
                address: $address,
                paymentMethod: $paymentMethod,
                couponCode: $request->validated('coupon_code'),
                notes: $request->validated('notes'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $firstOrder = $orders->first();
        $result = $this->paymentService->initiate($firstOrder, $request->user(), $paymentMethod);

        if ($result['success'] && isset($result['redirect_url'])) {
            return redirect($result['redirect_url']);
        }

        return redirect()
            ->route('orders.show', $firstOrder)
            ->with('error', $result['message'] ?? 'Checkout completed with payment issues.');
    }
}
