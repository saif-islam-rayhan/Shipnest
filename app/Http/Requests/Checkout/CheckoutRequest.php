<?php

namespace App\Http\Requests\Checkout;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'address_id' => [
                'required_without:new_address.recipient_name',
                'nullable',
                'integer',
                Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'new_address.recipient_name' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'new_address.phone' => ['required_without:address_id', 'nullable', 'string', 'max:20'],
            'new_address.address_line1' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'new_address.city' => ['required_without:address_id', 'nullable', 'string', 'max:100'],
            'new_address.district' => ['required_without:address_id', 'nullable', 'string', 'max:100'],
            'new_address.thana' => ['nullable', 'string', 'max:100'],
            'new_address.postal_code' => ['nullable', 'string', 'max:20'],
            'new_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_address.label' => ['nullable', 'string', 'max:50'],
            'new_address.is_default' => ['nullable', 'boolean'],
            'shipping_method' => ['required', 'string', Rule::in(array_keys(config('shipping.methods', [])))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Cash])],
            'cod_shipping_payment' => ['nullable', Rule::in(['bkash', 'nagad'])],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
