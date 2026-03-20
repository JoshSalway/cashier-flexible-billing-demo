<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DemoController::class, 'index']);
Route::get('/docs', [DemoController::class, 'docs']);
Route::get('/stream/{scenario}', [DemoController::class, 'stream']);
