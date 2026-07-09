<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'backend', 'framework' => 'laravel']));

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'backend']));
