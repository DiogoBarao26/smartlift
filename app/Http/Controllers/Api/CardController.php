<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Support\ApiResponse;

class CardController extends Controller
{
    public function show(string $cardCode)
    {
        $person = Person::query()
            ->where('card_code', strtoupper(trim($cardCode)))
            ->first();

        if ($person === null) {
            return ApiResponse::error('Cartão não encontrado.', 404);
        }

        return ApiResponse::ok(
            (new PersonResource($person))->resolve(),
            'Cartão localizado.',
        );
    }
}
