<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
  Route::post('/register',   [AuthController::class, 'register']);
  Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
  Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
  Route::post('/login',      [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
  Route::get('/me',      [AuthController::class, 'me']);
  Route::post('/logout', [AuthController::class, 'logout']);

  // Profil
  Route::post('/update-profile',       [AuthController::class, 'updateProfile']);
  Route::post('/verify-email-change',  [AuthController::class, 'verifyEmailChange']);
  Route::post('/resend-email-otp',     [AuthController::class, 'resendEmailChangeOtp']);
  Route::post('/cancel-email-change',  [AuthController::class, 'cancelEmailChange']);
});
