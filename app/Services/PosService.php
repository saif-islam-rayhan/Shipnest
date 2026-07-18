<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosService
{
    /**
     * @param  list<array{variant_id:int, quantity:int, unit_price?:float, discount?:float}>  $items
     * @return Collection<int, Order>
     */
    public function checkout(
        User $cashier,
        array $items,
        ?int $customerId = null,
        float $invoiceDiscount = 0,
        float $invoiceDiscountPercent = 0,
        ?string $notes = null,
        string $paymentMethod = 'cash',
        ?float $amountPaid = null,
    ): Collection {
        if ($items === []) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $customer = $customerId
            ? User::query()->findOrFail($customerId)
            : $this->walkInCustomer();

        $method = PaymentMethod::tryFrom($paymentMethod) ?? PaymentMethod::Cash;
        $taxRate = (float) config('shipnest.pos.tax_rate', 0);

        $lines = collect($items)->map(function (array $row) {
            $variant = ProductVariant::query()
                ->with(['product.merchant'])
                ->findOrFail($row['variant_id']);

            $product = $variant->product;

            if (! $product || $product->status !== ProductStatus::Active) {
                throw new \InvalidArgumentException("Product {$variant->sku} is unavailable.");
            }

            $qty = max(1, (int) $row['quantity']);
            if ($variant->stock < $qty) {
                throw new \InvalidArgumentException("Insufficient stock for {$product->name} ({$variant->sku}).");
            }

            $unitPrice = isset($row['unit_price']) ? (float) $row['unit_price'] : (float) $variant->price;
            $lineDiscount = max(0, (float) ($row['discount'] ?? 0));
            $lineSubtotal = max(0, ($unitPrice * $qty) - $lineDiscount);

            return [
                'variant' => $variant,
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'line_subtotal' => $lineSubtotal,
            ];
        });

        $subtotal = round($lines->sum('line_subtotal'), 2);
        $itemDiscount = round($lines->sum('discount'), 2);

        if ($invoiceDiscountPercent > 0) {
            $invoiceDiscount = round($subtotal * ($invoiceDiscountPercent / 100), 2);
        }
        $invoiceDiscount = max(0, min($invoiceDiscount, $subtotal));

        $taxable = max(0, $subtotal - $invoiceDiscount);
        $tax = round($taxable * ($taxRate / 100), 2);
        $totalBeforeRound = $taxable + $tax;
        $total = round($totalBeforeRound);
        $rounding = round($total - $totalBeforeRound, 2);

        $grouped = $lines->groupBy(fn (array $line) => $line['product']->merchant_id);

        $shopKeys = $grouped->keys()->values();
        $lastKey = $shopKeys->last();
        $allocatedDiscount = 0.0;
        $allocatedTax = 0.0;

        $orders = DB::transaction(function () use (
            $customer, $cashier, $method, $notes, $grouped, $subtotal,
            $invoiceDiscount, $tax, $rounding, $shopKeys, $lastKey,
            &$allocatedDiscount, &$allocatedTax, $amountPaid
        ) {
            $orders = collect();

            foreach ($shopKeys as $shopId) {
                $shopLines = $grouped->get($shopId);
                $shopSubtotal = round($shopLines->sum('line_subtotal'), 2);
                $shopShare = $subtotal > 0 ? ($shopSubtotal / $subtotal) : 0;

                if ($shopId === $lastKey) {
                    $shopDiscount = round($invoiceDiscount - $allocatedDiscount, 2);
                    $shopTax = round($tax - $allocatedTax, 2);
                    $shopRounding = $rounding;
                } else {
                    $shopDiscount = round($invoiceDiscount * $shopShare, 2);
                    $shopTax = round($tax * $shopShare, 2);
                    $shopRounding = 0;
                    $allocatedDiscount += $shopDiscount;
                    $allocatedTax += $shopTax;
                }

                $shopTotal = round($shopSubtotal - $shopDiscount + $shopTax + $shopRounding, 2);

                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $customer->id,
                    'status' => OrderStatus::Delivered->value,
                    'subtotal' => $shopSubtotal,
                    'discount' => $shopDiscount,
                    'shipping_charge' => 0,
                    'shipping_method' => 'pos_pickup',
                    'tax' => $shopTax,
                    'total' => $shopTotal,
                    'payment_method' => $method->value,
                    'payment_status' => PaymentStatus::Paid->value,
                    'payment_reference' => $amountPaid !== null
                        ? 'POS paid: '.number_format($amountPaid, 2)
                        : 'POS sale by '.$cashier->name,
                    'shipping_address_id' => null,
                    'coupon_id' => null,
                    'note' => trim(($notes ? $notes."\n" : '').'[POS] Cashier: '.$cashier->name) ?: null,
                    'delivered_at' => now(),
                ]);

                foreach ($shopLines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];
                    $product = $line['product'];

                    $order->items()->create([
                        'merchant_id' => $product->merchant_id,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant->name,
                        'sku' => $variant->sku,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount' => $line['discount'],
                        'total' => $line['line_subtotal'],
                        'status' => OrderStatus::Delivered->value,
                    ]);

                    $variant->decrement('stock', $line['quantity']);
                }

                $order->statusHistories()->create([
                    'status' => OrderStatus::Delivered->value,
                    'comment' => 'POS sale completed',
                    'created_by' => $cashier->id,
                ]);

                $orders->push($order->load(['items', 'user']));
            }

            return $orders;
        });

        return $orders;
    }

    public function walkInCustomer(): User
    {
        $email = config('shipnest.pos.walk_in_email', 'walkin@shipnest.local');

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $user = User::query()->create([
            'name' => 'Walk-in Customer',
            'email' => $email,
            'phone' => null,
            'password' => Hash::make(Str::random(32)),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('customer');
        }

        return $user;
    }

    /**
     * @param  list<array{variant_id:int, quantity:int, unit_price?:float, discount?:float}>  $items
     * @return array{subtotal: float, item_discount: float, invoice_discount: float, tax: float, rounding: float, total: float, tax_rate: float}
     */
    public function calculateTotals(
        array $items,
        float $invoiceDiscount = 0,
        float $invoiceDiscountPercent = 0,
    ): array {
        $taxRate = (float) config('shipnest.pos.tax_rate', 0);
        $itemDiscount = 0;
        $subtotal = 0;

        foreach ($items as $row) {
            $variant = ProductVariant::query()->find($row['variant_id']);
            if (! $variant) {
                continue;
            }
            $qty = max(1, (int) $row['quantity']);
            $unitPrice = isset($row['unit_price']) ? (float) $row['unit_price'] : (float) $variant->price;
            $lineDiscount = max(0, (float) ($row['discount'] ?? 0));
            $itemDiscount += $lineDiscount;
            $subtotal += max(0, ($unitPrice * $qty) - $lineDiscount);
        }

        $subtotal = round($subtotal, 2);
        $itemDiscount = round($itemDiscount, 2);

        if ($invoiceDiscountPercent > 0) {
            $invoiceDiscount = round($subtotal * ($invoiceDiscountPercent / 100), 2);
        }
        $invoiceDiscount = max(0, min($invoiceDiscount, $subtotal));

        $taxable = max(0, $subtotal - $invoiceDiscount);
        $tax = round($taxable * ($taxRate / 100), 2);
        $totalBeforeRound = $taxable + $tax;
        $total = round($totalBeforeRound);
        $rounding = round($total - $totalBeforeRound, 2);

        return [
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscount,
            'invoice_discount' => $invoiceDiscount,
            'tax' => $tax,
            'rounding' => $rounding,
            'total' => $total,
            'tax_rate' => $taxRate,
        ];
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'POS-'.strtoupper(Str::random(8));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
