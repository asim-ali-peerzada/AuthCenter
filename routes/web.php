<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\OAuth2Controller;
use App\Http\Controllers\Auth\EmailVerificationController;
Route::get('/', function () {
    return view('welcome');
});

// OAuth2 endpoints
Route::get('/authorize', [OAuth2Controller::class, 'authorize'])->name('oauth.authorize');

// Signed email verification route for JobFinder users
Route::get('/email/verify/jobfinder', [EmailVerificationController::class, 'verifyJobfinder'])
    ->name('verify.jobfinder.email')
    ->middleware('signed');

