<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Prunes push subscriptions that can never deliver again. Endpoints the push
 * service reports as expired (404/410) are already removed at send time by the
 * webpush channel's ReportHandler, so this cleaner targets what that cannot see:
 *
 *   - Orphaned rows whose owning model was hard-deleted (the morph target no
 *     longer exists). Soft-deleted owners are preserved.
 *   - With --days, subscriptions not refreshed within that window (opt-in, since
 *     a valid subscription for an idle user is not otherwise re-saved).
 */
#[Signature('app:clean-push-subscriptions {--days= : Also delete subscriptions not refreshed within this many days}')]
#[Description('Delete stale push subscriptions (orphaned owners; optionally long-unrefreshed)')]
class CleanStalePushSubscriptions extends Command
{
    public function handle(): int
    {
        /** @var class-string<Model> $model */
        $model = config('webpush.model');

        $orphaned = $this->deleteOrphaned($model);
        $aged = $this->deleteAged($model);

        $summary = "{$orphaned} orphaned";

        if ($this->option('days') !== null) {
            $summary .= ", {$aged} unrefreshed";
        }

        $this->info("Push subscriptions cleaned: {$summary}.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function deleteOrphaned(string $model): int
    {
        $deleted = 0;

        $types = $model::query()->distinct()->pluck('subscribable_type');

        foreach ($types as $type) {
            if (! is_string($type) || $type === '' || ! class_exists($type)) {
                // The owning model no longer exists in the codebase at all.
                $deleted += $model::query()->where('subscribable_type', $type)->delete();

                continue;
            }

            /** @var Model $related */
            $related = new $type;
            $keyName = $related->getKeyName();
            $table = $related->getTable();

            // Query the raw owner table (no Eloquent scopes) so soft-deleted
            // owners still count as present — only hard-deleted owners are pruned.
            $deleted += $model::query()
                ->where('subscribable_type', $type)
                ->whereNotIn('subscribable_id', function ($query) use ($table, $keyName) {
                    $query->select($keyName)->from($table);
                })
                ->delete();
        }

        return $deleted;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function deleteAged(string $model): int
    {
        $days = $this->option('days');

        if ($days === null) {
            return 0;
        }

        return $model::query()
            ->where('updated_at', '<', now()->subDays((int) $days))
            ->delete();
    }
}
