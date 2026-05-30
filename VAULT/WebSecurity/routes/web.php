<?php

use App\Http\Controllers\Web\ProductsController;
use App\Http\Controllers\Web\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('layouts.master');
});
Route::get('/home', function () {
    return view('welcome');
});
Route::prefix('sandbox')->group(function () {
    Route::get('/', function () {
        return view('Sandbox');
    })->name('sandbox');

    Route::get('/even', function () {
        return view('even');
    })->name('sandbox.even');

    Route::get('/prime', function () {
        return view('prime');
    })->name('sandbox.prime');

    Route::get('/multiple/{j?}', function ($j = 5) {
        $j = (int) $j;
        return view('multiple', compact('j'));
    })->name('sandbox.multiple');
});

Route::get('register', [UsersController::class, 'register'])->name('register');
Route::post('register', [UsersController::class, 'doRegister'])->name('do_register');

Route::get('login', [UsersController::class, 'login'])->name('login');
Route::post('login', [UsersController::class, 'doLogin'])->name('do_login');

Route::get('logout', [UsersController::class, 'doLogout'])->name('do_logout');

Route::middleware('auth')->group(function () {

    Route::resource('products', ProductsController::class);

});