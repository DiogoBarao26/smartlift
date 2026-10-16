<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallElevatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_floor' => ['required', 'integer'],
            'requested_direction' => ['nullable', Rule::in(['up', 'down'])],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_floor.required' => 'Informe o andar de origem da chamada.',
            'requested_floor.integer' => 'O andar deve ser um número inteiro.',
        ];
    }
}
