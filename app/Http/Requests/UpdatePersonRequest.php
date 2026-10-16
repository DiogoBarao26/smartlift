<?php

namespace App\Http\Requests;

use App\Models\Elevator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'card_code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9][A-Z0-9\-]{2,39}$/',
                Rule::unique('people', 'card_code')->ignore($this->route('person')),
            ],
            'destination_floor' => ['sometimes', 'required', 'integer', "between:{$min},{$max}"],
            'weight_kg' => ['sometimes', 'required', 'numeric', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_code.unique' => 'Este código de cartão já está em uso.',
            'destination_floor.between' => 'O andar de destino está fora da faixa do prédio.',
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
