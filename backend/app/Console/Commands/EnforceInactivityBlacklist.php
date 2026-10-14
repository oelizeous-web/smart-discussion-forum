<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blacklist;

class EnforceInactivityBlacklist extends Command
{
    protected $signature = 'forum:check-inactivity';
    protected $description = 'Suspend users who missed their compliance deadline';

    public function handle()
    {
        // Find users in the compliance period whose timer ran out
        $expiredUsers = Blacklist::where('status', 'COMPLIANCE_PERIOD')
            ->where('expiry_date', '<', now())
            ->get();

        foreach ($expiredUsers as $record) {
            $record->update([
                'status' => 'SUSPENDED',
                // Now the expiry_date becomes the un-ban date (e.g., banned for 7 days)
                'expiry_date' => now()->addDays(7), 
            ]);
        }

        $this->info('Suspended ' . $expiredUsers->count() . ' non-compliant users.');
    }
}

