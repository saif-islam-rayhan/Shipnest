<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;

class CodService extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Cod;
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        $symbol = config('shipnest.currency_symbol', '৳');
        $dueOnDelivery = max(0, (float) $order->total);
        $dueOnDeliveryFormatted = number_format($dueOnDelivery);
        $transactionId = $this->generateTransactionId();

        $payment = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'method' => PaymentMethod::Cod->value,
            'status' => PaymentStatus::Pending->value,
            'amount' => $dueOnDelivery,
            'gateway_response' => [
                'type' => 'cod',
                'order_ids' => $options['order_ids'] ?? [$order->id],
            ],
        ]);

        $order->update([
            'payment_status' => PaymentStatus::Pending->value,
            'payment_transaction_id' => $transactionId,
        ]);

        return [
            'success' => true,
            'confirmed' => true,
            'payment' => $payment,
            'redirect_url' => route('order.success', $order->order_number),
            'message' => "Order confirmed! Pay {$symbol}{$dueOnDeliveryFormatted} in cash on delivery.",
        ];
    }

    public function verify(array $payload): PaymentTransaction
    {
        return PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['transaction_id'] ?? '')
            ->firstOrFail();
    }
}
