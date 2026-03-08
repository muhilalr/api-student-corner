<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RollbackPendingEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:rollback-pending-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback email baru jika OTP tidak diverifikasi lebih dari 1 hari';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updated = User::whereNotNull('pending_email')
            ->where('otp_expires_at', '<', now()->subDay())
            ->update([
                'pending_email' => null,
                'otp_code' => null,
                'otp_expires_at' => null,
            ]);

        $this->info("$updated email yang tidak diverifikasi telah di-rollback.");
    }
}
