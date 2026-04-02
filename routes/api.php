<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\InsurerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public endpoints
Route::get('/insurers', [InsurerController::class, 'index']);
// Route::post('/claims', [ClaimController::class, 'store']); 

// Protected endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/claims', [ClaimController::class, 'index']);
    Route::get('/claims/{claim}', [ClaimController::class, 'show']);
});
