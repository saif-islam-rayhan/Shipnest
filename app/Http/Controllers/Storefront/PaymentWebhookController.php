<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function stripe(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $this->paymentService->handleStripeWebhook($payload, $signature);
        } catch (\Throwable) {
            return response('Invalid webhook', SymfonyResponse::HTTP_BAD_REQUEST);
        }

        return response('OK', SymfonyResponse::HTTP_OK);
    }

    public function ipn(Request $request, string $gateway): Response
    {
        if ($gateway === 'sslcommerz') {
            try {
                $this->paymentService->handleSslcommerzIpn($request->all());
            } catch (\Throwable) {
                return response('FAILED', SymfonyResponse::HTTP_BAD_REQUEST);
            }

            return response('SUCCESS', SymfonyResponse::HTTP_OK);
        }

        return response('Unsupported gateway', SymfonyResponse::HTTP_NOT_FOUND);
    }
}
