<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
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
        $cart->load(['items.product.shop', 'items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $groupedItems = $this->cartService->getItemsGroupedByShop($cart);
        $addresses = $request->user()->addresses()->latest()->get();
        $paymentMethods = PaymentMethod::cases();
        $shippingMethods = config('shipping.methods', []);
        $merchantNumbers = config('payment.merchant_numbers', []);
        $cartTotals = $this->cartService->getTotals($cart);
        $shippingMethod = old('shipping_method', 'standard');
        $orderTotals = $this->orderService->calculateTotal($cart, $shippingMethod, $cart->coupon_code);

        return view('storefront.checkout.index', compact(
            'cart',
            'groupedItems',
            'addresses',
            'paymentMethods',
            'shippingMethods',
            'merchantNumbers',
            'cartTotals',
            'orderTotals',
        ));
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $paymentMethod = PaymentMethod::from($validated['payment_method']);
        $paymentReference = $validated['payment_reference'] ?? null;

        if (in_array($paymentMethod, [PaymentMethod::Bkash, PaymentMethod::Nagad], true) && empty($paymentReference)) {
            return back()->with('error', 'Please enter your payment reference number.')->withInput();
        }

        try {
            $orders = $this->orderService->placeOrder($request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $firstOrder = $orders->first();
        $firstOrder->setAttribute('total', $orders->sum('total'));

        $shippingCharge = (float) $orders->sum('shipping_charge');

        if ($paymentMethod === PaymentMethod::Cod && $shippingCharge > 0) {
            if (empty($validated['cod_shipping_payment'])) {
                return back()->with('error', 'Please select bKash or Nagad to pay the shipping charge.')->withInput();
            }
            if (empty($paymentReference)) {
                return back()->with('error', 'Please pay the shipping charge first and enter the transaction ID.')->withInput();
            }
        }

        $result = $this->paymentService->initiate(
            $firstOrder,
            $request->user(),
            $paymentMethod,
            $paymentReference,
            [
                'cod_shipping_payment' => $validated['cod_shipping_payment'] ?? null,
            ],
        );

        if ($result['success']) {
            return redirect($result['redirect_url'] ?? route('order.success', $firstOrder->order_number))
                ->with('success', $result['message'] ?? 'Order placed successfully!');
        }

        return redirect()
            ->route('orders.show', $firstOrder)
            ->with('error', $result['message'] ?? 'Checkout completed with payment issues.');
    }
}
