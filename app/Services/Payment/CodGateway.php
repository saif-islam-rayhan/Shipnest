<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CodGateway extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Cod;
    }

    public function initiate(Order $order, User $user): array
    {
        $transactionId = $this->generateTransactionId();

        $payment = $this->createPaymentRecord($order, $user, $transactionId);

        $order->update([
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Confirmed,
        ]);

        return [
            'success' => true,
            'payment' => $payment,
            'redirect_url' => route('orders.show', $order),
        ];
    }

    public function verify(array $payload): Payment
    {
        $payment = Payment::query()
            ->with('order')
            ->where('transaction_id', $payload['transaction_id'])
            ->firstOrFail();

        return $payment;
    }
}
