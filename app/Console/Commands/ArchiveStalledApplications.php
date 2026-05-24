<?php

namespace App\Console\Commands;

use App\Models\IncompleteApplication;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:archive-stalled-applications')]
#[Description('Archive stalled applications after 14 days and delete expired ones after 90 days')]
class ArchiveStalledApplications extends Command
{
    public function handle()
    {
        // Archive records inactive for 14+ days
        $archived = IncompleteApplication::stale()->update(['archived_at' => now()]);
        $this->info("Archived {$archived} stalled applications.");

        // Permanently delete records inactive for 90+ days
        $deleted = IncompleteApplication::expired()->delete();
        $this->info("Deleted {$deleted} expired applications.");
    }
}
