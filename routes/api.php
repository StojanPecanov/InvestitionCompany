<?php
use App\Http\Controllers\ClientController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;

Route::post('/clients', [ClientController::class, 'store']);

Route::get('/transactions', [TransactionsController::class, 'index']);
Route::post('/transactions', [TransactionsController::class, 'store']);

Route::get('/clients/{client}/summary', [
    TransactionsController::class,
    'clientSummary'
]);