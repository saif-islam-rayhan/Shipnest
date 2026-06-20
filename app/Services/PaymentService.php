<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payment\BkashService;
use App\Services\Payment\CodService;
use App\Services\Payment\NagadService;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SSLCommerzService;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CodService $codService,
        private readonly SSLCommerzService $sslcommerzService,
        private readonly BkashService $bkashService,
        private readonly NagadService $nagadService,
    ) {}

    public function initiate(Order $order, User $user, PaymentMethod $method, ?string $reference = null, array $options = []): array
    {
        $result = $this->resolveGateway($method)->initiate($order, $user, $reference, $options);

        if ($result['success'] && ($result['confirmed'] ?? false)) {
            $this->orderService->confirmOrder($order);
        }

        return $result;
    }

    public function verify(PaymentMethod $method, array $payload): PaymentTransaction
    {
        return $this->resolveGateway($method)->verify($payload);
    }

    public function handleCallback(string $gateway, array $payload): array
    {
        $method = PaymentMethod::from($gateway);
        $payment = $this->verify($method, $payload);
        $success = in_array($payment->status, [PaymentStatus::Completed->value, PaymentStatus::Paid->value], true);

        if ($success) {
            $this->orderService->confirmOrder($payment->order);
        }

        return [
            'success' => $success,
            'payment' => $payment,
            'order' => $payment->order->fresh(),
            'redirect_url' => $success
                ? route('order.success', $payment->order->order_number)
                : route('orders.show', $payment->order),
        ];
    }

    protected function resolveGateway(PaymentMethod $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::Cod => $this->codService,
            PaymentMethod::Sslcommerz => $this->sslcommerzService,
            PaymentMethod::Bkash => $this->bkashService,
            PaymentMethod::Nagad => $this->nagadService,
            default => throw new InvalidArgumentException("Unsupported payment method: {$method->value}"),
        };
    }
}
