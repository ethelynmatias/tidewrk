<?php

use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::post('/upload', [
            UploadController::class,
            'store'
        ]);

    });
