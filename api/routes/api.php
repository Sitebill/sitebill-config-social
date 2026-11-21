<?php

use App\Configs\Social\Http\UploadController;
use Illuminate\Support\Facades\Route;

// Подключается и ядром (конфиг, /api/v1/social/), и рантаймом приложений (пакет, /api/v1/apps/<id>/) — один файл.
Route::post('upload', [UploadController::class, 'store'])->middleware('throttle:20,1')->name('upload');
