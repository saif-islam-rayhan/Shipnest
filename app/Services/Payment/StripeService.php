<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Stripe;
    }

    public function isConfigured(): bool
    {
        return filled(config('payment.stripe.secret'));
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Stripe is not configured. Add STRIPE_SECRET to your environment.',
            ];
        }

        Stripe::setApiKey(config('payment.stripe.secret'));

        $transactionId = $this->generateTransactionId();
        $orderIds = $options['order_ids'] ?? [$order->id];
        $payment = $this->createPaymentRecord($order, $user, $transactionId, [
            'order_ids' => $orderIds,
            'type' => 'stripe_checkout',
        ]);

        $currency = strtolower(config('payment.stripe.currency', 'usd'));
        $amount = (int) round((float) $order->total * 100);

        if ($amount < 1) {
            $payment->update(['status' => PaymentStatus::Failed->value]);

            return [
                'success' => false,
                'payment' => $payment,
                'message' => 'Order total is too low for Stripe payment.',
            ];
        }

        try {
            $session = Session::create([
                'mode' => 'payment',
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'ShipNest Order #'.$order->order_number,
                            'description' => count($orderIds) > 1
                                ? 'Multi-shop checkout ('.count($orderIds).' orders)'
                                : 'Online order payment',
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'transaction_id' => $transactionId,
                    'order_id' => (string) $order->id,
                    'order_ids' => implode(',', $orderIds),
                ],
                'success_url' => route('payment.callback', ['gateway' => 'stripe']).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.index'),
            ]);

            $payment->update([
                'gateway_response' => array_merge($payment->gateway_response ?? [], [
                    'stripe_session_id' => $session->id,
                ]),
            ]);

            $order->update(['payment_transaction_id' => $transactionId]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $session->url,
            ];
        } catch (\Throwable $e) {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'payment' => $payment,
                'message' => 'Stripe payment could not be started.',
            ];
        }
    }

    public function verify(array $payload): PaymentTransaction
    {
        $sessionId = $payload['session_id'] ?? null;

        if (! $sessionId || ! $this->isConfigured()) {
            throw new \InvalidArgumentException('Invalid Stripe callback.');
        }

        Stripe::setApiKey(config('payment.stripe.secret'));
        $session = Session::retrieve($sessionId);
        $transactionId = $session->metadata['transaction_id'] ?? '';

        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        if ($session->payment_status === 'paid') {
            $this->markCompleted($payment, [
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $session->payment_intent,
                'amount_total' => $session->amount_total,
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => array_merge($payment->gateway_response ?? [], [
                    'stripe_session' => $session->toArray(),
                ]),
            ]);
        }

        return $payment->fresh(['order']);
    }

    public function handleWebhook(string $payload, ?string $signature): ?PaymentTransaction
    {
        $secret = config('payment.stripe.webhook_secret');

        if (! $secret || ! $signature) {
            return null;
        }

        Stripe::setApiKey(config('payment.stripe.secret'));
        $event = Webhook::constructEvent($payload, $signature, $secret);

        if ($event->type !== 'checkout.session.completed') {
            return null;
        }

        /** @var Session $session */
        $session = $event->data->object;
        $transactionId = $session->metadata['transaction_id'] ?? '';

        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $payment || $payment->status === PaymentStatus::Completed->value) {
            return $payment;
        }

        if ($session->payment_status === 'paid') {
            $this->markCompleted($payment, [
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $session->payment_intent,
                'webhook' => true,
            ]);
        }

        return $payment->fresh(['order']);
    }

    public function refund(PaymentTransaction $payment, float $amount): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Stripe not configured.'];
        }

        $intentId = $payment->gateway_response['stripe_payment_intent'] ?? null;

        if (! $intentId) {
            return ['success' => false, 'message' => 'No Stripe payment intent found for this transaction.'];
        }

        Stripe::setApiKey(config('payment.stripe.secret'));

        try {
            $refund = Refund::create([
                'payment_intent' => $intentId,
                'amount' => (int) round($amount * 100),
            ]);

            return ['success' => true, 'gateway_response' => $refund->toArray()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function markCompleted(PaymentTransaction $payment, array $extra = []): void
    {
        $payment->update([
            'status' => PaymentStatus::Completed->value,
            'gateway_response' => array_merge($payment->gateway_response ?? [], $extra),
        ]);

        $payment->order->update([
            'payment_status' => PaymentStatus::Completed->value,
            'payment_transaction_id' => $payment->transaction_id,
        ]);
    }
}
