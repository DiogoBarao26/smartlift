<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoardElevatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_code' => ['required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_code.required' => 'Informe o código do cartão.',
        ];
    }
}
