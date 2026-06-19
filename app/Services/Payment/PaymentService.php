<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly CodGateway $codGateway,
        private readonly SslcommerzGateway $sslcommerzGateway,
        private readonly BkashGateway $bkashGateway,
        private readonly NagadGateway $nagadGateway,
    ) {}

    public function initiate(Order $order, User $user, PaymentMethod $method): array
    {
        return $this->resolveGateway($method)->initiate($order, $user);
    }

    public function verify(PaymentMethod $method, array $payload)
    {
        return $this->resolveGateway($method)->verify($payload);
    }

    protected function resolveGateway(PaymentMethod $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::Cod => $this->codGateway,
            PaymentMethod::Sslcommerz => $this->sslcommerzGateway,
            PaymentMethod::Bkash => $this->bkashGateway,
            PaymentMethod::Nagad => $this->nagadGateway,
        };
    }
}
