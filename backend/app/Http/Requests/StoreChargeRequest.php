<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('charge', $this->route('lease')) ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:CANTEEN,APPLIANCE,OTHER'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
