<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlightElevatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_id' => ['nullable', 'integer'],
        ];
    }
}
