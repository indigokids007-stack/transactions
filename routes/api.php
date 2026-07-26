<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['status' => 'ok'])->name('health');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
