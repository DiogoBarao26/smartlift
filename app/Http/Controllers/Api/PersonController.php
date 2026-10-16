<?php

namespace App\Http\Controllers\Api;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdateCardStatusRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Support\ApiResponse;

class PersonController extends Controller
{
    public function index()
    {
        $people = Person::query()->orderBy('name')->get();

        return ApiResponse::ok(
            PersonResource::collection($people)->resolve(),
            'Pessoas listadas.',
        );
    }

    public function store(StorePersonRequest $request)
    {
        $data = $request->validated();

        $person = Person::query()->create([
            'name' => $data['name'],
            'card_code' => $data['card_code'],
            'destination_floor' => $data['destination_floor'],
            'weight_kg' => $data['weight_kg'],
            'card_status' => $data['card_status'] ?? CardStatus::Active,
        ]);

        return ApiResponse::ok(
            (new PersonResource($person))->resolve(),
            'Pessoa e cartão cadastrados.',
            201,
        );
    }

    public function update(UpdatePersonRequest $request, Person $person)
    {
        $person->fill($request->validated());
        $person->save();

        return ApiResponse::ok(
            (new PersonResource($person))->resolve(),
            'Cadastro atualizado.',
        );
    }

    public function updateStatus(UpdateCardStatusRequest $request, Person $person)
    {
        $person->card_status = CardStatus::from($request->validated('card_status'));
        $person->save();

        return ApiResponse::ok(
            (new PersonResource($person))->resolve(),
            $person->card_status === CardStatus::Active
                ? 'Cartão ativado.'
                : 'Cartão bloqueado.',
        );
    }
}
