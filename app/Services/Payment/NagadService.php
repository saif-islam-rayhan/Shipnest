<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class NagadService extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Nagad;
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        if ($reference) {
            return $this->initiateManual($order, $user, $reference);
        }

        $transactionId = $this->generateTransactionId();
        $payment = $this->createPaymentRecord($order, $user, $transactionId);

        $sensitiveData = [
            'merchantId' => config('payment.nagad.merchant_id'),
            'datetime' => now()->format('YmdHis'),
            'orderId' => $transactionId,
            'challenge' => config('payment.nagad.challenge'),
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4' => request()->ip(),
            'X-KM-Client-Type' => 'PC_WEB',
        ])->post(
            config('payment.nagad.base_url').'/check-out/initialize/'.config('payment.nagad.merchant_id').'/'.$transactionId,
            [
                'accountNumber' => config('payment.nagad.merchant_number'),
                'dateTime' => $sensitiveData['datetime'],
                'sensitiveData' => $this->encryptSensitiveData($sensitiveData),
                'signature' => $this->generateSignature($sensitiveData),
            ]
        );

        if ($response->successful() && $response->json('status') === 'Success') {
            $order->update(['payment_transaction_id' => $transactionId]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $response->json('callBackUrl'),
            ];
        }

        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'gateway_response' => $response->json() ?? [],
        ]);

        return [
            'success' => false,
            'payment' => $payment,
            'message' => $response->json('message') ?? 'Nagad payment initiation failed.',
        ];
    }

    protected function initiateManual(Order $order, User $user, string $reference): array
    {
        $transactionId = $this->generateTransactionId();
        $payment = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'method' => PaymentMethod::Nagad->value,
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->total,
            'gateway_response' => ['reference' => $reference, 'type' => 'manual'],
        ]);

        $order->update([
            'payment_reference' => $reference,
            'payment_transaction_id' => $transactionId,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        return [
            'success' => true,
            'payment' => $payment,
            'redirect_url' => route('order.success', $order->order_number),
            'message' => 'Payment submitted! Your order will be confirmed after we verify your Nagad payment.',
            'confirmed' => false,
        ];
    }

    public function verify(array $payload): PaymentTransaction
    {
        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['orderId'] ?? $payload['transaction_id'] ?? '')
            ->firstOrFail();

        if (($payload['status'] ?? '') === 'Success') {
            $payment->update([
                'status' => PaymentStatus::Completed->value,
                'gateway_response' => $payload,
            ]);

            $payment->order->update([
                'payment_status' => PaymentStatus::Completed->value,
                'payment_transaction_id' => $payment->transaction_id,
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'gateway_response' => $payload,
            ]);
        }

        return $payment->fresh(['order']);
    }

    protected function encryptSensitiveData(array $data): string
    {
        $publicKey = config('payment.nagad.public_key');
        $json = json_encode($data);
        openssl_public_encrypt($json, $encrypted, $publicKey);

        return base64_encode($encrypted);
    }

    protected function generateSignature(array $data): string
    {
        $privateKey = config('payment.nagad.private_key');
        $json = json_encode($data);
        openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
