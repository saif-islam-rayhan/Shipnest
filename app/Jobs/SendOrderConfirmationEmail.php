<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
    ) {}

    public function handle(): void
    {
        $this->order->load(['user', 'items', 'shippingAddress']);

        if ($this->order->user?->email) {
            Mail::to($this->order->user->email)->send(new OrderConfirmationMail($this->order));
        }
    }
}
