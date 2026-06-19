<?php

use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/search', SearchController::class)->name('api.search');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load(['merchant', 'addresses']);
});
