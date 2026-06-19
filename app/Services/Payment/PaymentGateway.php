<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

abstract class PaymentGateway
{
    abstract public function initiate(Order $order, User $user): array;

    abstract public function verify(array $payload): Payment;

    abstract public function method(): PaymentMethod;

    protected function createPaymentRecord(Order $order, User $user, string $transactionId): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'gateway' => $this->method()->value,
            'status' => PaymentStatus::Processing,
            'amount' => $order->total,
            'currency' => 'BDT',
        ]);
    }

    protected function generateTransactionId(): string
    {
        return 'TXN-'.strtoupper(Str::random(12));
    }
}
