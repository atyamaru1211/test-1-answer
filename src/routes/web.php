<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ContactController::class, 'index']);
Route::post('/confirm', [ContactController::class, 'confirm']);
Route::post('/thanks', [ContactController::class, 'store']);

Route::middleware('auth')->group(function () { //authミドルウェアは認証済みのユーザのみルートにアクセスできるようにする。
    Route::get('/admin', [ContactController::class, 'admin']); //つまり、これら４つはログインしている管理者のみが利用できる
    Route::get('/search', [ContactController::class, 'search']);
    Route::post('/delete', [ContactController::class, 'destroy']);
    Route::post('/export', [ContactController::class, 'export']);
});
