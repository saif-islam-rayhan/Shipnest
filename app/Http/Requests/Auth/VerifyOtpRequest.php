<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+880|0)1[3-9]\d{8}$/'],
            'otp' => ['required', 'string', 'digits:'.config('sms.otp.length', 6)],
            'type' => ['required', 'in:registration,login'],
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
