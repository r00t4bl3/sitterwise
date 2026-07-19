<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('documents:migrate-to-private {--apply : Actually move the files (dry-run by default)}')]
#[Description('Move existing certification documents (CPR, TrustLine, certifications) from the public disk to the private documents disk.')]
class MoveCertificationDocumentsToPrivateDisk extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $public = Storage::disk('public');
        $documents = Storage::disk('documents');

        $paths = DB::table('caregiver_certifications')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->pluck('file_path')
            ->unique();

        $moved = 0;
        $alreadyPrivate = 0;
        $missing = 0;

        foreach ($paths as $path) {
            if ($documents->exists($path)) {
                $alreadyPrivate++;

                continue;
            }

            if (! $public->exists($path)) {
                $missing++;
                $this->warn("Missing on both disks: {$path}");

                continue;
            }

            if ($apply) {
                // Keep the same relative path so the stored file_path values
                // stay valid — only the backing disk changes.
                $documents->put($path, $public->get($path));
                $public->delete($path);
            }

            $moved++;
        }

        $verb = $apply ? 'Moved' : 'Would move';
        $this->info("{$verb}: {$moved}, already private: {$alreadyPrivate}, missing: {$missing}.");

        if (! $apply) {
            $this->comment('Dry run. Re-run with --apply to move the files.');
        }

        return self::SUCCESS;
    }
}
