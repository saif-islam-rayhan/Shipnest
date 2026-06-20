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
        $shippingAmount = (float) $order->shipping_charge;
        $dueOnDelivery = max(0, (float) $order->subtotal - (float) $order->discount);
        $dueOnDeliveryFormatted = number_format($dueOnDelivery);

        if ($shippingAmount <= 0) {
            $transactionId = $this->generateTransactionId();
            $payment = PaymentTransaction::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'method' => PaymentMethod::Cod->value,
                'status' => PaymentStatus::Completed->value,
                'amount' => 0,
                'gateway_response' => ['type' => 'cod_no_shipping'],
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

        if (empty($reference)) {
            return [
                'success' => false,
                'message' => 'Please pay the shipping charge first via bKash or Nagad and enter the transaction ID.',
            ];
        }

        $shippingPaymentMethod = $options['cod_shipping_payment'] ?? null;
        if (! in_array($shippingPaymentMethod, ['bkash', 'nagad'], true)) {
            return [
                'success' => false,
                'message' => 'Please select bKash or Nagad to pay the shipping charge.',
            ];
        }

        $transactionId = $this->generateTransactionId();
        $payment = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'method' => $shippingPaymentMethod,
            'status' => PaymentStatus::Pending->value,
            'amount' => $shippingAmount,
            'gateway_response' => [
                'type' => 'cod_shipping_upfront',
                'reference' => $reference,
                'shipping_method' => $shippingPaymentMethod,
            ],
        ]);

        $order->update([
            'payment_status' => PaymentStatus::Pending->value,
            'payment_reference' => $reference,
            'payment_transaction_id' => $transactionId,
        ]);

        $shippingFormatted = number_format($shippingAmount);

        return [
            'success' => true,
            'confirmed' => false,
            'payment' => $payment,
            'redirect_url' => route('order.success', $order->order_number),
            'message' => "Shipping payment ({$symbol}{$shippingFormatted}) submitted. Order will confirm after verification. Pay {$symbol}{$dueOnDeliveryFormatted} in cash on delivery.",
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
