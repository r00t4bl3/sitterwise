<?php

namespace App\Console\Commands;

use App\Models\Caregiver;
use Illuminate\Console\Command;

class RegenerateCaregiverSlugs extends Command
{
    protected $signature = 'app:regenerate-caregiver-slugs';

    protected $description = 'Regenerate caregiver slugs using full last name before numeric suffix';

    public function handle()
    {
        $this->info('Regenerating caregiver slugs...');

        $caregivers = Caregiver::all()->filter(fn ($c) => preg_match('/-\d+$/', $c->slug));

        if ($caregivers->isEmpty()) {
            $this->info('No caregivers with numeric suffixed slugs found.');

            return Command::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($caregivers as $caregiver) {
            $oldSlug = $caregiver->slug;

            $newSlug = Caregiver::generateSlug(
                "{$caregiver->first_name} {$caregiver->last_name}",
                $caregiver->id,
            );

            if ($newSlug === $oldSlug) {
                $skipped++;

                continue;
            }

            $caregiver->update(['slug' => $newSlug]);
            $this->line("  {$oldSlug} → {$newSlug}");
            $updated++;
        }

        $this->newLine();
        $this->info("Done. {$updated} updated, {$skipped} already optimal.");

        return Command::SUCCESS;
    }
}
