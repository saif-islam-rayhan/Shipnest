<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoiceService
{
    public function download(Order $order): Response
    {
        $order->load(['items.product.images', 'shippingAddress', 'user', 'shop', 'payment']);

        return Pdf::loadView('account.orders.invoice', compact('order'))
            ->setPaper('a4')
            ->download("invoice-{$order->order_number}.pdf");
    }
}
