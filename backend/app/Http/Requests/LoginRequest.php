<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'required_without:identifier', 'email'],
            'identifier' => ['nullable', 'required_without:email', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
