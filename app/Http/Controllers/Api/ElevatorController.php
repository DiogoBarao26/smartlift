<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlightElevatorRequest;
use App\Http\Requests\BoardElevatorRequest;
use App\Http\Requests\CallElevatorRequest;
use App\Http\Requests\UpdateElevatorSettingsRequest;
use App\Http\Resources\ElevatorEventResource;
use App\Services\ElevatorService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ElevatorController extends Controller
{
    public function __construct(private readonly ElevatorService $elevators) {}

    public function show()
    {
        $result = $this->elevators->state();

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function call(CallElevatorRequest $request)
    {
        $result = $this->elevators->call(
            (int) $request->validated('requested_floor'),
            $request->validated('requested_direction'),
        );

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function board(BoardElevatorRequest $request)
    {
        $result = $this->elevators->board($request->validated('card_code'));

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function alight(AlightElevatorRequest $request)
    {
        $personId = $request->validated('person_id');
        $result = $this->elevators->alight($personId !== null ? (int) $personId : null);

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function depart()
    {
        $result = $this->elevators->depart();

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function updateSettings(UpdateElevatorSettingsRequest $request)
    {
        $result = $this->elevators->updateSettings($request->validated());

        return ApiResponse::ok($result['data'], $result['message']);
    }

    public function history(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $events = $this->elevators->history($perPage);

        return ApiResponse::ok([
            'items' => ElevatorEventResource::collection($events->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ], 'Histórico de operações.');
    }
}
