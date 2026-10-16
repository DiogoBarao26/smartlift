<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_status' => ['required', Rule::in(['active', 'blocked'])],
        ];
    }

    public function messages(): array
    {
        return [
            'card_status.required' => 'Informe o status do cartão.',
            'card_status.in' => 'O status deve ser ativo ou bloqueado.',
        ];
    }
}
