<?php

use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Preekrooster backend']);
});

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('public.storage');

Route::get('/reset-password/{token}', function (): never {
    abort(404);
})->name('password.reset');
