<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class CleanupExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:cleanup-expired-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired reservations back to open status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired reservations...');

        $expired = Booking::where('status', 'reserved')
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<', now())
            ->update([
                'reserved_by' => null,
                'reservation_expires_at' => null,
                'status' => 'received',
            ]);

        if ($expired > 0) {
            $this->info("Released {$expired} expired reservation(s) back to open status.");
        } else {
            $this->info('No expired reservations found.');
        }

        return Command::SUCCESS;
    }
}
