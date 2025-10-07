<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\OAuth2Controller;
Route::get('/', function () {
    return view('welcome');
});

// OAuth2 endpoints
Route::get('/authorize', [OAuth2Controller::class, 'authorize'])->name('oauth.authorize');

