<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Str;

abstract class PaymentGateway
{
    abstract public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array;

    abstract public function verify(array $payload): PaymentTransaction;

    abstract public function method(): PaymentMethod;

    protected function createPaymentRecord(Order $order, User $user, string $transactionId, array $meta = []): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'method' => $this->method()->value,
            'status' => 'processing',
            'amount' => $meta['amount'] ?? $order->total,
            'gateway_response' => $meta,
        ]);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    protected function generateTransactionId(): string
    {
        return 'TXN-'.strtoupper(Str::random(12));
    }
}
