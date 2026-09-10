<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\ExtractionController;
use App\Http\Controllers\Api\V1\FiscalDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/tokens/create', function (Request $request) {
    $token = $request->user()->createToken($request->token_name);

    return ['token' => $token->plainTextToken];
});

Route::prefix('v1/teams/{team:slug}')
    ->middleware(['auth:sanctum'])
    ->name('api.')
    ->group(function () {
        Route::name('v1.')->group(function () {
            Route::name('extractions.')->group(function () {
                Route::get('/', [ExtractionController::class, 'index'])
                    ->middleware('throttle:60,1')
                    ->name('index');
                Route::get('{extraction}', [ExtractionController::class, 'show'])
                    ->middleware('throttle:60,1')
                    ->name('show');
                Route::post('/', [ExtractionController::class, 'store'])
                    ->middleware(['throttle:20,1', 'ability:extractions:create'])
                    ->name('store');
            });
            Route::name('documents.')->group(function () {
                Route::get('/', [FiscalDocumentController::class, 'index'])
                    ->middleware('throttle:60,1')
                    ->name('index');
                Route::get('{document}', [FiscalDocumentController::class, 'show'])
                    ->middleware('throttle:60,1')
                    ->name('show');
            });
        });
    });