<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::view('/search', 'app');
Route::view('/categories', 'app');
Route::view('/how-it-works', 'app');
Route::view('/register', 'app');
Route::view('/login', 'app');
Route::view('/profile', 'app');
Route::view('/saved', 'app');
Route::view('/tracker', 'app');
Route::view('/my-opportunities', 'app');
Route::view('/verify-email/{user}/{hash}', 'app')->name('email-verification.page');
Route::view('/categories/{slug}', 'app')->where('slug', '[A-Za-z0-9-]+');
Route::view('/opportunities/{slug}', 'app')->where('slug', '[A-Za-z0-9-]+');
