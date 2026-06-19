<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class MerchantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+880|0)1[3-9]\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:merchants,shop_slug'],
            'district' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Bangladeshi mobile number (e.g. 01XXXXXXXXX).',
            'shop_slug.alpha_dash' => 'Shop URL may only contain letters, numbers, dashes and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => app(\App\Services\OtpService::class)->normalizePhone($this->input('phone')),
            ]);
        }
    }
}
