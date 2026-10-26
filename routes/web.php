<?php

use App\Http\Controllers\ExtractionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ExtractionController::class, 'dashboard'])->name('dashboard');
Route::get('/modules/{provider}', [ExtractionController::class, 'module'])->name('modules.show');
Route::post('/modules/{provider}/extractions', [ExtractionController::class, 'store'])->middleware('throttle:10,1')->name('extractions.store');
Route::get('/modules/{provider}/extractions/{extraction}', [ExtractionController::class, 'show'])->name('extractions.show');
Route::get('/modules/{provider}/extractions/{extraction}/status', [ExtractionController::class, 'status'])->name('extractions.status');
Route::get('/modules/{provider}/extractions/{extraction}/export/{format}', [ExtractionController::class, 'export'])->name('extractions.export');
