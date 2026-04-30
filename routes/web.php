<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturaController;

Route::get('/',          [FacturaController::class, 'index']);
Route::post('/procesar', [FacturaController::class, 'procesar']);
