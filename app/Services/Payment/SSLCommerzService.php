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

    public function testConnection(?string $storeId = null, ?string $storePassword = null, ?string $apiUrl = null): array
    {
        $storeId = $storeId ?? config('payment.sslcommerz.store_id');
        $storePassword = $storePassword ?? config('payment.sslcommerz.store_password');
        $apiUrl = rtrim($apiUrl ?? config('payment.sslcommerz.api_url', 'https://sandbox.sslcommerz.com'), '/');

        if (! filled($storeId) || ! filled($storePassword)) {
            return [
                'success' => false,
                'message' => 'Store ID and Store Password are required.',
            ];
        }

        $response = Http::asForm()->timeout(20)->post($apiUrl.'/gwprocess/v4/api.php', [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => 10,
            'currency' => 'BDT',
            'tran_id' => 'TESTCONN_'.time(),
            'success_url' => url('/'),
            'fail_url' => url('/'),
            'cancel_url' => url('/'),
            'cus_name' => 'ShipNest Test',
            'cus_email' => 'test@shipnest.com',
            'cus_phone' => '01700000000',
            'shipping_method' => 'NO',
            'product_name' => 'Connection Test',
            'product_category' => 'Ecommerce',
            'product_profile' => 'general',
        ]);

        $status = $response->json('status');
        $reason = strtolower((string) ($response->json('failedreason') ?? ''));

        if ($status === 'SUCCESS') {
            return [
                'success' => true,
                'message' => 'SSLCommerz credentials verified. Checkout redirect is ready.',
            ];
        }

        if (str_contains($reason, 'store') && (
            str_contains($reason, 'credential')
            || str_contains($reason, 'invalid')
            || str_contains($reason, 'password')
            || str_contains($reason, 'passwd')
        )) {
            return [
                'success' => false,
                'message' => 'Invalid Store ID or Store Password. Check sandbox mode and API URL.',
            ];
        }

        if ($response->successful() && $status === 'FAILED' && ! str_contains($reason, 'store')) {
            return [
                'success' => true,
                'message' => 'Store credentials accepted by SSLCommerz.',
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('failedreason') ?? 'Could not verify credentials. Check API URL and sandbox mode.',
        ];
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'SSLCommerz is not configured.',
            ];
        }

        $order->loadMissing('shippingAddress');

        $transactionId = $this->generateTransactionId();
        $orderIds = $options['order_ids'] ?? [$order->id];
        $amount = (float) ($options['payment_amount'] ?? $order->total);
        $address = $order->shippingAddress;

        $payment = $this->createPaymentRecord($order, $user, $transactionId, [
            'order_ids' => $orderIds,
            'type' => 'sslcommerz',
            'amount' => $amount,
        ]);

        $postData = [
            'store_id' => config('payment.sslcommerz.store_id'),
            'store_passwd' => config('payment.sslcommerz.store_password'),
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $transactionId,
            'success_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'success']),
            'fail_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'fail']),
            'cancel_url' => route('payment.callback', ['gateway' => 'sslcommerz', 'status' => 'cancel']),
            'ipn_url' => route('payment.ipn', ['gateway' => 'sslcommerz']),
            'cus_name' => $address?->recipient_name ?: $user->name,
            'cus_email' => $user->email ?: 'customer@shipnest.com',
            'cus_phone' => $address?->phone ?: ($user->phone ?? '01700000000'),
            'cus_add1' => $address?->address_line1 ?: 'Dhaka, Bangladesh',
            'cus_city' => $address?->city ?: 'Dhaka',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => 'ShipNest Order #'.$order->order_number,
            'product_category' => 'Ecommerce',
            'product_profile' => 'general',
        ];

        $response = Http::asForm()
            ->timeout(30)
            ->post(rtrim(config('payment.sslcommerz.api_url'), '/').'/gwprocess/v4/api.php', $postData);

        $payload = $response->json() ?? [];
        $gatewayUrl = $payload['GatewayPageURL']
            ?? $payload['redirectGatewayURL']
            ?? null;

        if ($response->successful() && ($payload['status'] ?? null) === 'SUCCESS' && filled($gatewayUrl)) {
            $order->update(['payment_transaction_id' => $transactionId]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $gatewayUrl,
            ];
        }

        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'gateway_response' => $payload,
        ]);

        return [
            'success' => false,
            'payment' => $payment,
            'message' => $payload['failedreason'] ?? 'Payment initiation failed.',
        ];
    }

    public function verify(array $payload): PaymentTransaction
    {
        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['tran_id'] ?? $payload['transaction_id'] ?? '')
            ->firstOrFail();

        $existingMeta = $payment->gateway_response ?? [];
        $valId = $payload['val_id'] ?? '';

        if ($valId === '') {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => array_merge($existingMeta, [
                    'callback_payload' => $payload,
                    'validation_error' => 'Missing val_id from SSLCommerz callback.',
                ]),
            ]);

            return $payment->fresh(['order']);
        }

        $validationData = [
            'val_id' => $valId,
            'store_id' => config('payment.sslcommerz.store_id'),
            'store_passwd' => config('payment.sslcommerz.store_password'),
            'format' => 'json',
        ];

        $response = Http::timeout(30)->get(
            rtrim(config('payment.sslcommerz.api_url'), '/').'/validator/api/validationserverAPI.php',
            $validationData
        );

        $validation = $response->json() ?? [];

        if ($response->successful() && ($validation['status'] ?? null) === 'VALID') {
            $payment->update([
                'status' => PaymentStatus::Completed->value,
                'gateway_response' => array_merge($existingMeta, [
                    'validation' => $validation,
                    'callback_payload' => $payload,
                ]),
            ]);

            $payment->order->update([
                'payment_status' => PaymentStatus::Completed->value,
                'payment_transaction_id' => $payment->transaction_id,
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => array_merge($existingMeta, [
                    'validation' => $validation,
                    'callback_payload' => $payload,
                ]),
            ]);

            $payment->order->update(['payment_status' => PaymentStatus::Failed->value]);
        }

        return $payment->fresh(['order']);
    }
}
