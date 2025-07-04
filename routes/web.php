<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController as WebAuthController;
use App\Http\Controllers\Web\ProductController as WebProductController;


// Authentication Routes
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->name('login.post');
Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Product Routes (Protected)
Route::middleware('auth')->group(function () {
    Route::get('/', [WebProductController::class, 'index'])->name('home');
    Route::get('/products/create', [WebProductController::class, 'create'])->name('products.create');
    Route::post('/products', [WebProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [WebProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [WebProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [WebProductController::class, 'destroy'])->name('products.destroy');
});