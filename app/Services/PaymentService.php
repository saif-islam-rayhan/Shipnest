<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\BkashService;
use App\Services\Payment\CodService;
use App\Services\Payment\NagadService;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SSLCommerzService;
use App\Services\Payment\StripeService;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CodService $codService,
        private readonly SSLCommerzService $sslcommerzService,
        private readonly BkashService $bkashService,
        private readonly NagadService $nagadService,
        private readonly StripeService $stripeService,
    ) {}

    public function initiate(Order $order, User $user, PaymentMethod $method, ?string $reference = null, array $options = []): array
    {
        $options['order_ids'] = $options['order_ids'] ?? [$order->id];

        $result = $this->resolveGateway($method)->initiate($order, $user, $reference, $options);

        if ($result['success'] && ($result['confirmed'] ?? false)) {
            $this->confirmRelatedOrders($result['payment'] ?? null, [$order->id]);
        }

        return $result;
    }

    public function verify(PaymentMethod $method, array $payload): PaymentTransaction
    {
        return $this->resolveGateway($method)->verify($payload);
    }

    public function handleCallback(string $gateway, array $payload, ?string $callbackStatus = null): array
    {
        $method = PaymentMethod::from($gateway);

        if ($method === PaymentMethod::Sslcommerz && in_array($callbackStatus, ['fail', 'cancel'], true)) {
            return $this->handleSslcommerzFailure($payload, $callbackStatus);
        }

        $payment = $this->verify($method, $payload);
        $success = in_array($payment->status, [PaymentStatus::Completed->value, PaymentStatus::Paid->value], true);

        if ($success) {
            $this->confirmRelatedOrders($payment);
        }

        return [
            'success' => $success,
            'payment' => $payment,
            'order' => $payment->order->fresh(),
            'redirect_url' => $success
                ? route('order.success', $payment->order->order_number)
                : route('account.orders.show', $payment->order->order_number),
            'flash' => $success
                ? 'Payment completed successfully.'
                : 'Payment failed or could not be verified.',
        ];
    }

    protected function handleSslcommerzFailure(array $payload, string $callbackStatus): array
    {
        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['tran_id'] ?? $payload['transaction_id'] ?? '')
            ->first();

        if ($payment) {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => array_merge($payment->gateway_response ?? [], [
                    'callback_status' => $callbackStatus,
                    'callback_payload' => $payload,
                ]),
            ]);

            $payment->order?->update(['payment_status' => PaymentStatus::Failed->value]);
        }

        $orderNumber = $payment?->order?->order_number;

        return [
            'success' => false,
            'payment' => $payment,
            'order' => $payment?->order,
            'redirect_url' => $orderNumber
                ? route('account.orders.show', $orderNumber)
                : route('account.orders.index'),
            'flash' => $callbackStatus === 'cancel'
                ? 'Payment was cancelled.'
                : 'Payment failed.',
        ];
    }

    public function handleSslcommerzIpn(array $payload): ?PaymentTransaction
    {
        $payment = $this->sslcommerzService->verify($payload);

        if (in_array($payment->status, [PaymentStatus::Completed->value, PaymentStatus::Paid->value], true)) {
            $this->confirmRelatedOrders($payment);
        }

        return $payment;
    }

    public function handleStripeWebhook(string $payload, ?string $signature): ?PaymentTransaction
    {
        $payment = $this->stripeService->handleWebhook($payload, $signature);

        if ($payment && in_array($payment->status, [PaymentStatus::Completed->value, PaymentStatus::Paid->value], true)) {
            $this->confirmRelatedOrders($payment);
        }

        return $payment;
    }

    public function gatewaySupportsRedirect(PaymentMethod $method): bool
    {
        return $this->resolveGateway($method)->isConfigured();
    }

    public function approveManualPayment(PaymentTransaction $payment, User $admin): PaymentTransaction
    {
        $payment->update([
            'status' => PaymentStatus::Completed->value,
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'verified_by' => $admin->id,
                'verified_at' => now()->toIso8601String(),
            ]),
        ]);

        $payment->order->update([
            'payment_status' => PaymentStatus::Completed->value,
            'payment_transaction_id' => $payment->transaction_id,
        ]);

        $this->confirmRelatedOrders($payment);

        return $payment->fresh(['order', 'user']);
    }

    public function rejectManualPayment(PaymentTransaction $payment, User $admin, ?string $note = null): PaymentTransaction
    {
        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'rejected_by' => $admin->id,
                'rejected_at' => now()->toIso8601String(),
                'reject_note' => $note,
            ]),
        ]);

        $payment->order->update(['payment_status' => PaymentStatus::Failed->value]);

        return $payment->fresh(['order', 'user']);
    }

    public function processRefund(Order $order, float $amount, User $admin, ?string $note = null): Refund
    {
        $payment = $order->latestPayment;
        $status = 'completed';
        $refundNote = $note;

        if ($payment && $order->payment_method === PaymentMethod::Stripe) {
            $result = $this->stripeService->refund($payment, $amount);
            if (! ($result['success'] ?? false)) {
                $status = 'failed';
                $refundNote = trim(($note ? $note.' — ' : '').($result['message'] ?? 'Stripe refund failed.'));
            } else {
                $refundNote = trim(($note ? $note.' — ' : '').'Stripe refund processed.');
            }
        }

        $refund = Refund::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $amount,
            'method' => $order->payment_method->value,
            'status' => $status,
            'note' => $refundNote,
            'processed_by' => $admin->id,
        ]);

        if ($status === 'completed') {
            $order->update(['payment_status' => PaymentStatus::Refunded->value]);
        }

        return $refund;
    }

    public function confirmRelatedOrders(?PaymentTransaction $payment = null, ?array $orderIds = null): void
    {
        $ids = $orderIds
            ?? ($payment?->gateway_response['order_ids'] ?? null)
            ?? ($payment ? [$payment->order_id] : []);

        Order::query()
            ->whereIn('id', $ids)
            ->each(function (Order $order) {
                $order->update(['payment_status' => PaymentStatus::Completed->value]);
                $this->orderService->confirmOrder($order);
            });
    }

    protected function resolveGateway(PaymentMethod $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::Cod => $this->codService,
            PaymentMethod::Sslcommerz => $this->sslcommerzService,
            PaymentMethod::Bkash => $this->bkashService,
            PaymentMethod::Nagad => $this->nagadService,
            PaymentMethod::Stripe => $this->stripeService,
            default => throw new InvalidArgumentException("Unsupported payment method: {$method->value}"),
        };
    }
}
