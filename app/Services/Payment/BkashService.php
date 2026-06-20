<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class BkashService extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Bkash;
    }

    public function initiate(Order $order, User $user, ?string $reference = null, array $options = []): array
    {
        if ($reference) {
            return $this->initiateManual($order, $user, $reference);
        }

        $transactionId = $this->generateTransactionId();
        $payment = $this->createPaymentRecord($order, $user, $transactionId);
        $token = $this->getAccessToken();

        if (! $token) {
            $payment->update(['status' => PaymentStatus::Failed->value]);

            return [
                'success' => false,
                'payment' => $payment,
                'message' => 'Failed to authenticate with bKash.',
            ];
        }

        $response = Http::withToken($token)
            ->withHeaders(['X-APP-Key' => config('payment.bkash.app_key')])
            ->post(config('payment.bkash.base_url').'/tokenized/checkout/create', [
                'mode' => '0011',
                'payerReference' => (string) $user->id,
                'callbackURL' => route('payment.callback', ['gateway' => 'bkash']),
                'amount' => (string) $order->total,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $transactionId,
            ]);

        if ($response->successful() && $response->json('statusCode') === '0000') {
            $order->update(['payment_transaction_id' => $transactionId]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $response->json('bkashURL'),
            ];
        }

        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'gateway_response' => $response->json() ?? [],
        ]);

        return [
            'success' => false,
            'payment' => $payment,
            'message' => $response->json('statusMessage') ?? 'bKash payment initiation failed.',
        ];
    }

    protected function initiateManual(Order $order, User $user, string $reference): array
    {
        $transactionId = $this->generateTransactionId();
        $payment = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'method' => PaymentMethod::Bkash->value,
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
            'message' => 'Payment submitted! Your order will be confirmed after we verify your bKash payment.',
            'confirmed' => false,
        ];
    }

    public function verify(array $payload): PaymentTransaction
    {
        $paymentId = $payload['paymentID'] ?? $payload['payment_id'] ?? null;
        $payment = PaymentTransaction::query()
            ->with('order')
            ->where('transaction_id', $payload['merchantInvoiceNumber'] ?? $payload['transaction_id'] ?? '')
            ->firstOrFail();

        if (! $paymentId) {
            return $payment;
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['X-APP-Key' => config('payment.bkash.app_key')])
            ->post(config('payment.bkash.base_url').'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);

        if ($response->successful() && $response->json('statusCode') === '0000') {
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

    protected function getAccessToken(): ?string
    {
        $response = Http::withHeaders([
            'username' => config('payment.bkash.username'),
            'password' => config('payment.bkash.password'),
        ])->withHeaders(['X-APP-Key' => config('payment.bkash.app_key')])
            ->post(config('payment.bkash.base_url').'/tokenized/checkout/token/grant', [
                'app_key' => config('payment.bkash.app_key'),
                'app_secret' => config('payment.bkash.app_secret'),
            ]);

        return $response->json('id_token');
    }
}
