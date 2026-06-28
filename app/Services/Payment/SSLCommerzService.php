<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class SSLCommerzService extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Sslcommerz;
    }

    public function isConfigured(): bool
    {
        return filled(config('payment.sslcommerz.store_id'))
            && filled(config('payment.sslcommerz.store_password'));
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'SSLCommerz is not configured.',
            ];
        }

        $transactionId = $this->generateTransactionId();
        $orderIds = $options['order_ids'] ?? [$order->id];
        $payment = $this->createPaymentRecord($order, $user, $transactionId, [
            'order_ids' => $orderIds,
            'type' => 'sslcommerz',
        ]);

        $postData = [
            'store_id' => config('payment.sslcommerz.store_id'),
            'store_passwd' => config('payment.sslcommerz.store_password'),
            'total_amount' => $order->total,
            'currency' => 'BDT',
            'tran_id' => $transactionId,
            'success_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'success']),
            'fail_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'fail']),
            'cancel_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'cancel']),
            'ipn_url' => route('payment.ipn', ['gateway' => 'sslcommerz']),
            'cus_name' => $user->name,
            'cus_email' => $user->email,
            'cus_phone' => $user->phone ?? '01700000000',
            'shipping_method' => 'NO',
            'product_name' => 'ShipNest Order #'.$order->order_number,
            'product_category' => 'Ecommerce',
            'product_profile' => 'general',
        ];

        $response = Http::asForm()->post(config('payment.sslcommerz.api_url').'/gwprocess/v4/api.php', $postData);

        if ($response->successful() && ($response->json('status') === 'SUCCESS')) {
            $order->update(['payment_transaction_id' => $transactionId]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $response->json('GatewayPageURL'),
            ];
        }

        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'gateway_response' => $response->json() ?? [],
        ]);

        return [
            'success' => false,
            'payment' => $payment,
            'message' => $response->json('failedreason') ?? 'Payment initiation failed.',
        ];
    }

    public function verify(array $payload): PaymentTransaction
    {
        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['tran_id'] ?? $payload['transaction_id'] ?? '')
            ->firstOrFail();

        $validationData = [
            'val_id' => $payload['val_id'] ?? '',
            'store_id' => config('payment.sslcommerz.store_id'),
            'store_passwd' => config('payment.sslcommerz.store_password'),
            'format' => 'json',
        ];

        $response = Http::get(config('payment.sslcommerz.api_url').'/validator/api/validationserverAPI.php', $validationData);

        if ($response->successful() && $response->json('status') === 'VALID') {
            $payment->update([
                'status' => PaymentStatus::Completed->value,
                'gateway_response' => $response->json(),
            ]);

            $payment->order->update([
                'payment_status' => PaymentStatus::Completed->value,
                'payment_transaction_id' => $payment->transaction_id,
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => $response->json() ?? [],
            ]);
        }

        return $payment->fresh(['order']);
    }
}
