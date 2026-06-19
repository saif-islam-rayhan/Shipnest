<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BkashGateway extends PaymentGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Bkash;
    }

    public function initiate(Order $order, User $user): array
    {
        $transactionId = $this->generateTransactionId();
        $payment = $this->createPaymentRecord($order, $user, $transactionId);

        $token = $this->getAccessToken();

        if (! $token) {
            $payment->update(['status' => PaymentStatus::Failed]);

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
            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $response->json('bkashURL'),
                'bkash_payment_id' => $response->json('paymentID'),
            ];
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'gateway_response' => $response->json(),
        ]);

        return [
            'success' => false,
            'payment' => $payment,
            'message' => $response->json('statusMessage') ?? 'bKash payment initiation failed.',
        ];
    }

    public function verify(array $payload): Payment
    {
        $paymentId = $payload['paymentID'] ?? $payload['payment_id'];
        $payment = Payment::query()
            ->with('order')
            ->where('transaction_id', $payload['merchantInvoiceNumber'] ?? '')
            ->firstOrFail();

        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['X-APP-Key' => config('payment.bkash.app_key')])
            ->post(config('payment.bkash.base_url').'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);

        if ($response->successful() && $response->json('statusCode') === '0000') {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'gateway_response' => $response->json(),
                'paid_at' => now(),
            ]);

            $payment->order->update([
                'payment_status' => PaymentStatus::Completed,
                'paid_at' => now(),
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'gateway_response' => $response->json(),
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
