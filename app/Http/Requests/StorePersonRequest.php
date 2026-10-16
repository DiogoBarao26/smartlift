<?php

namespace App\Http\Requests;

use App\Models\Elevator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('card_code')) {
            $this->merge([
                'card_code' => strtoupper(trim((string) $this->input('card_code'))),
            ]);
        }
    }

    public function rules(): array
    {
        [$min, $max] = $this->floorRange();

        return [
            'name' => ['required', 'string', 'max:120'],
            'card_code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\-]{2,39}$/', 'unique:people,card_code'],
            'destination_floor' => ['required', 'integer', "between:{$min},{$max}"],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:500'],
            'card_status' => ['sometimes', Rule::in(['active', 'blocked'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome completo.',
            'card_code.required' => 'Informe o código do cartão.',
            'card_code.unique' => 'Este código de cartão já está em uso.',
            'card_code.regex' => 'O código deve conter letras, números ou hífen.',
            'destination_floor.between' => 'O andar de destino está fora da faixa do prédio.',
            'weight_kg.min' => 'O peso deve ser maior que zero.',
        ];
    }

    private function floorRange(): array
    {
        $elevator = Elevator::query()->first();

        return [
            $elevator->min_floor ?? 0,
            $elevator->max_floor ?? 100,
        ];
    }
}
