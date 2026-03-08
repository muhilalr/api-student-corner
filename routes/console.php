<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus user yang tidak verifikasi OTP lebih dari 1 hari
// Jalankan setiap jam
Schedule::command('users:delete-unverified')->hourly();

// Rollback email yang tidak diverifikasi
Schedule::command('users:rollback-pending-email')->daily();
