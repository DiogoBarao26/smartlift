<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateElevatorSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'min_floor' => ['required', 'integer', 'min:0', 'max:80'],
            'max_floor' => ['required', 'integer', 'max:100', 'gt:min_floor'],
            'max_weight_kg' => ['required', 'numeric', 'min:30', 'max:10000'],
            'max_passengers' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_floor.gt' => 'O andar máximo deve ser maior que o andar mínimo.',
            'max_weight_kg.min' => 'A capacidade de peso deve ser de pelo menos 30 kg.',
            'max_passengers.min' => 'O elevador deve transportar ao menos uma pessoa.',
        ];
    }
}
