<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { border-bottom: 2px solid #F57C00; padding-bottom: 16px; margin-bottom: 24px; }
        .brand { font-size: 24px; font-weight: bold; color: #F57C00; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; width: 300px; margin-left: auto; }
        .totals td { border: none; padding: 4px 8px; }
        .total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">ShipNest</div>
        <p>Invoice #{{ $order->order_number }}</p>
        <p>Date: {{ $order->created_at->format('M d, Y') }}</p>
    </div>

    <table style="border: none; margin-bottom: 24px;">
        <tr>
            <td style="border: none; width: 50%; vertical-align: top;">
                <strong>Bill To</strong><br>
                {{ $order->user->name }}<br>
                {{ $order->user->email }}<br>
                @if($order->user->phone){{ $order->user->phone }}@endif
            </td>
            <td style="border: none; width: 50%; vertical-align: top;">
                <strong>Ship To</strong><br>
                @if($order->shippingAddress)
                    {{ $order->shippingAddress->recipient_name }}<br>
                    {{ $order->shippingAddress->full_address }}<br>
                    {{ $order->shippingAddress->phone }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}@if($item->variant_name) ({{ $item->variant_name }})@endif</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ config('shipnest.currency_symbol') }}{{ number_format($item->unit_price) }}</td>
                    <td>{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td style="text-align:right">{{ config('shipnest.currency_symbol') }}{{ number_format($order->subtotal) }}</td></tr>
        <tr><td>Shipping</td><td style="text-align:right">{{ config('shipnest.currency_symbol') }}{{ number_format($order->shipping_fee) }}</td></tr>
        @if($order->discount > 0)
            <tr><td>Discount</td><td style="text-align:right">-{{ config('shipnest.currency_symbol') }}{{ number_format($order->discount) }}</td></tr>
        @endif
        <tr class="total-row"><td>Total</td><td style="text-align:right">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td></tr>
    </table>

    <p style="margin-top: 32px; font-size: 10px; color: #666;">
        Payment: {{ $order->payment_method->label() }} ({{ $order->payment_status->label() }})
    </p>
</body>
</html>
