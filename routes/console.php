<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus user yang tidak verifikasi OTP lebih dari 1 hari
Schedule::call(function () {
    User::where('is_verified', 0)
        ->where('otp_expires_at', '<', now()->subDay()) // lebih dari 1 hari
        ->delete();
})->daily();

// Rollback pending email kalau lebih dari 1 hari tidak diverifikasi (update email)
Schedule::call(function () {
    User::whereNotNull('pending_email')
        ->where('otp_expires_at', '<', now()->subDay()) // lebih dari 1 hari
        ->update([
            'pending_email' => null,
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);
})->daily();
