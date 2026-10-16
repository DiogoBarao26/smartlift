<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::ok(
            $this->statistics->overview($data['from'] ?? null, $data['to'] ?? null),
            'Indicadores gerais.',
        );
    }

    public function daily(Request $request)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        return ApiResponse::ok(
            $this->statistics->daily($data['date'] ?? null),
            'Indicadores do dia.',
        );
    }

    public function passengers(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::ok(
            $this->statistics->passengers($data['from'] ?? null, $data['to'] ?? null),
            'Indicadores de passageiros.',
        );
    }

    public function weight(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::ok(
            $this->statistics->weight($data['from'] ?? null, $data['to'] ?? null),
            'Indicadores de peso transportado.',
        );
    }
}
