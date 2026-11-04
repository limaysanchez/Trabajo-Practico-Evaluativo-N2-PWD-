<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageComparisonController;

Route::get('/', function () {
    return "Hola Laravel";
});
Route::get('/', [ImageComparisonController::class, 'index'])
    ->name('image.index');

Route::post('/compare', [ImageComparisonController::class, 'compare'])
    ->name('image.compare');