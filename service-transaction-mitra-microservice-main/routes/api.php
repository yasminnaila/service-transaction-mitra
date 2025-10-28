<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\TransactionMitraController;

Route::get('/transactions-mitra', [TransactionMitraController::class, 'index']);
Route::get('/transactions-mitra/{id}', [TransactionMitraController::class, 'show']);
Route::post('/transactions-mitra', [TransactionMitraController::class, 'store']);
Route::put('/transactions-mitra/{id}', [TransactionMitraController::class, 'update']);
Route::patch('/transactions-mitra/{id}/{status}', [TransactionMitraController::class, 'updateStatus']);
Route::delete('/transactions-mitra/{id}', [TransactionMitraController::class, 'destroy']);
