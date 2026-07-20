<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('documents:migrate-to-private {--apply : Actually move the files (dry-run by default)}')]
#[Description('Move existing sensitive documents (CPR, TrustLine, certifications, signed agreements) onto the private documents disk.')]
class MoveCertificationDocumentsToPrivateDisk extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->migrateCertifications($apply);
        $this->migrateAgreements($apply);

        if (! $apply) {
            $this->comment('Dry run. Re-run with --apply to move the files.');
        }

        return self::SUCCESS;
    }

    /**
     * Certification documents were stored on the public disk at a relative
     * path; move the bytes to the private disk keeping the same path so the
     * stored file_path values stay valid.
     */
    protected function migrateCertifications(bool $apply): void
    {
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
                $this->warn("Certification missing on both disks: {$path}");

                continue;
            }

            if ($apply) {
                $documents->put($path, $public->get($path));
                $public->delete($path);
            }

            $moved++;
        }

        $verb = $apply ? 'Moved' : 'Would move';
        $this->info("Certifications — {$verb}: {$moved}, already private: {$alreadyPrivate}, missing: {$missing}.");
    }

    /**
     * Signed agreements stored an absolute filesystem path (via
     * file_put_contents). Move each into the private disk under a relative
     * path and rewrite pdf_path so the download route reads from the disk.
     */
    protected function migrateAgreements(bool $apply): void
    {
        $documents = Storage::disk('documents');

        $agreements = DB::table('caregiver_agreements')
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->get(['id', 'caregiver_id', 'pdf_path']);

        $moved = 0;
        $alreadyPrivate = 0;
        $missing = 0;

        foreach ($agreements as $agreement) {
            $path = $agreement->pdf_path;

            // Already a relative documents-disk path.
            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $alreadyPrivate++;

                continue;
            }

            $relative = "agreements/{$agreement->caregiver_id}/".basename($path);

            // The stored path is absolute and was baked in at generation time, so
            // it can point at an old deployment directory (e.g. after a domain or
            // path rename). Resolve the real file from this deployment's storage
            // first, then fall back to the stored absolute path.
            $source = collect([
                storage_path('app/'.$relative),
                $path,
            ])->first(fn ($candidate) => is_file($candidate));

            if (! $source) {
                $missing++;
                $this->warn("Agreement file missing (looked in storage/app/{$relative} and {$path})");

                continue;
            }

            if ($apply) {
                $documents->put($relative, file_get_contents($source));

                // Only delete the source if it is not already the documents-disk
                // location we just wrote to.
                if (realpath($source) !== realpath(storage_path('app/documents/'.$relative))) {
                    @unlink($source);
                }

                DB::table('caregiver_agreements')
                    ->where('id', $agreement->id)
                    ->update(['pdf_path' => $relative]);
            }

            $moved++;
        }

        $verb = $apply ? 'Moved' : 'Would move';
        $this->info("Agreements — {$verb}: {$moved}, already private: {$alreadyPrivate}, missing: {$missing}.");
    }
}
