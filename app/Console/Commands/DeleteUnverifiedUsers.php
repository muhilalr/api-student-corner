<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DeleteUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete-unverified';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus akun yang belum diverifikasi lebih dari 24 jam';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = User::where('is_verified', 0)
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->delete();

        $this->info("$deleted akun tidak terverifikasi telah dihapus.");
    }
}
