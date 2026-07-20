<?php

namespace App\Console\Commands;

use App\Models\CaregiverAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

#[Signature('agreements:regenerate-missing {--apply : Actually regenerate and store the PDFs (dry-run by default)}')]
#[Description('Regenerate caregiver agreement PDFs whose files are missing, from stored application data, using the application submission date as the signed date.')]
class RegenerateMissingAgreements extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $documents = Storage::disk('documents');

        $agreements = CaregiverAgreement::with('caregiver.application')->get();

        $regenerated = 0;
        $present = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($agreements as $agreement) {
            if ($this->fileExists($agreement, $documents)) {
                $present++;

                continue;
            }

            $caregiver = $agreement->caregiver;
            $application = $caregiver?->application;

            if (! $application || ! is_array($application->data)) {
                $skipped++;
                $this->warn("Agreement #{$agreement->id} (caregiver {$agreement->caregiver_id}): no application data; skipped.");

                continue;
            }

            $view = "pdfs.caregiver-{$agreement->type}";

            if (! View::exists($view)) {
                $skipped++;
                $this->warn("Agreement #{$agreement->id}: no PDF view for type '{$agreement->type}'; skipped.");

                continue;
            }

            $relative = "agreements/{$caregiver->id}/{$agreement->type}.pdf";

            if ($apply) {
                // One failure must not abort the whole run, and pdf_path must
                // never be stamped unless the file actually landed on disk —
                // otherwise the DB points at a document that isn't there.
                try {
                    $pdf = Pdf::loadView($view, [
                        'caregiver' => $caregiver,
                        'data' => $application->data,
                    ]);

                    $written = $documents->put($relative, $pdf->output());

                    if ($written === false || ! $documents->exists($relative)) {
                        throw new \RuntimeException("write to documents disk failed for {$relative}");
                    }

                    $agreement->update([
                        'pdf_path' => $relative,
                        // The signing date is taken from when the applicant
                        // submitted their application, not the regeneration time.
                        'signed_at' => $application->submitted_at ?? $agreement->signed_at,
                    ]);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Agreement #{$agreement->id} (caregiver {$caregiver->id}, {$agreement->type}): {$e->getMessage()}");

                    continue;
                }
            }

            $regenerated++;
            $this->line("Agreement #{$agreement->id} (caregiver {$caregiver->id}, {$agreement->type}) → {$relative}");
        }

        $verb = $apply ? 'Regenerated' : 'Would regenerate';
        $this->info("{$verb}: {$regenerated}, already present: {$present}, skipped (no data): {$skipped}, failed: {$failed}.");

        if (! $apply) {
            $this->comment('Dry run. Re-run with --apply to regenerate and store the PDFs.');
        }

        // Non-zero exit on failure so a deploy/automation step surfaces it.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Whether the agreement's PDF is actually retrievable, checking the private
     * disk (new relative paths) and, for legacy absolute paths, this
     * deployment's storage and the stored path.
     */
    protected function fileExists(CaregiverAgreement $agreement, Filesystem $documents): bool
    {
        $path = $agreement->pdf_path;

        if (! $path) {
            return false;
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $documents->exists($path);
        }

        $relative = "agreements/{$agreement->caregiver_id}/".basename($path);

        return is_file(storage_path('app/'.$relative)) || is_file($path);
    }
}
