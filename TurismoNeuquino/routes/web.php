<?php

use App\Http\Controllers\ImageComparisonController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ImageComparisonController::class, 'index'])
    ->name('image.index');

Route::post('/compare', [ImageComparisonController::class, 'compare'])
    ->name('image.compare');

Route::get('/resultado', [ImageComparisonController::class, 'result'])
    ->name('image.result');

Route::get('/temporal/imagen', [ImageComparisonController::class, 'temporaryImage'])
    ->name('image.temporary');

Route::post('/registrar', [ImageComparisonController::class, 'register'])
    ->name('image.register');

Route::post('/descartar', [ImageComparisonController::class, 'discard'])
    ->name('image.discard');
