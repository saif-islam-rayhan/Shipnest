<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'reason' => ['required', 'string', 'in:defective,wrong_item,not_as_described,changed_mind,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
