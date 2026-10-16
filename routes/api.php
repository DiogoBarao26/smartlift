<?php

use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ElevatorController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/people', [PersonController::class, 'index']);
Route::get('/cards/{cardCode}', [CardController::class, 'show'])->where('cardCode', '[A-Za-z0-9\-]+');

Route::middleware('admin')->group(function () {
    Route::post('/people', [PersonController::class, 'store']);
    Route::patch('/people/{person}', [PersonController::class, 'update']);
    Route::patch('/people/{person}/status', [PersonController::class, 'updateStatus']);
    Route::patch('/elevator/settings', [ElevatorController::class, 'updateSettings']);
});

Route::get('/elevator', [ElevatorController::class, 'show']);
Route::post('/elevator/call', [ElevatorController::class, 'call']);
Route::post('/elevator/board', [ElevatorController::class, 'board']);
Route::post('/elevator/alight', [ElevatorController::class, 'alight']);
Route::post('/elevator/depart', [ElevatorController::class, 'depart']);
Route::get('/elevator/history', [ElevatorController::class, 'history']);

Route::get('/statistics', [StatisticsController::class, 'index']);
Route::get('/statistics/daily', [StatisticsController::class, 'daily']);
Route::get('/statistics/passengers', [StatisticsController::class, 'passengers']);
Route::get('/statistics/weight', [StatisticsController::class, 'weight']);
