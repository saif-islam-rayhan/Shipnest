<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): View
    {
        $payments = PaymentTransaction::query()
            ->with(['order', 'user'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'pending'))
            ->when($request->input('method'), fn ($q, $m) => $q->where('method', $m))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function approve(PaymentTransaction $payment): RedirectResponse
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be approved.');
        }

        $this->paymentService->approveManualPayment($payment, auth()->user());

        return back()->with('success', 'Payment verified and order confirmed.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be rejected.');
        }

        $this->paymentService->rejectManualPayment(
            $payment,
            auth()->user(),
            $request->input('note'),
        );

        return back()->with('success', 'Payment rejected.');
    }
}
