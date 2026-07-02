<?php

namespace App\Console\Commands;

use App\Models\ClientChild;
use App\Services\ImportUserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:cleanup-junk-children {--dry-run : Report what would be removed without changing anything} {--purge : Permanently delete matching client_children (including ones already soft-deleted) instead of soft-deleting}')]
#[Description('Remove junk "None"/"N/A" children with no birth data from client profiles and booking groups')]
class CleanupJunkChildren extends Command
{
    /**
     * Services that never involve children, so any birthless child entry on
     * their booking groups is junk (e.g. count-only "Child 2" placeholders).
     *
     * @var list<string>
     */
    private const CHILD_FREE_SERVICES = ['petsitter', 'companion_care'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $purge = (bool) $this->option('purge');

        if ($dryRun) {
            $this->info('DRY RUN — no records will be changed.');
        }

        if ($purge && ! $dryRun) {
            $this->warn('PURGE — matching client_children will be permanently deleted (not recoverable).');
        }

        $childRows = $this->cleanClientChildren($dryRun, $purge);
        [$groupsAffected, $entriesRemoved] = $this->cleanBookingGroupChildren($dryRun);

        $this->line('');
        $verb = $purge ? 'purged' : 'removed';
        $this->info("client_children rows {$verb}: {$childRows}");
        $this->info("booking_groups updated: {$groupsAffected} (child entries removed: {$entriesRemoved})");

        return self::SUCCESS;
    }

    private function cleanClientChildren(bool $dryRun, bool $purge): int
    {
        $query = ClientChild::query()->whereNull('birth_date');

        // A purge also sweeps up rows a prior (soft-delete) run already removed.
        if ($purge) {
            $query->withTrashed();
        }

        $ids = $query
            ->get(['id', 'name'])
            ->filter(fn (ClientChild $child) => ImportUserService::isJunkChildName($child->name))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        if (! $dryRun) {
            if ($purge) {
                ClientChild::withTrashed()->whereIn('id', $ids)->forceDelete();
            } else {
                ClientChild::whereIn('id', $ids)->delete();
            }
        }

        return $ids->count();
    }

    /**
     * @return array{0: int, 1: int} [groupsAffected, entriesRemoved]
     */
    private function cleanBookingGroupChildren(bool $dryRun): array
    {
        $groupsAffected = 0;
        $entriesRemoved = 0;

        DB::table('booking_groups')
            ->whereNotNull('children')
            ->select('id', 'children', 'service_type')
            ->orderBy('id')
            ->chunk(500, function ($groups) use ($dryRun, &$groupsAffected, &$entriesRemoved) {
                foreach ($groups as $group) {
                    // Pet/companion services have no children, so any birthless
                    // entry there is junk (even count-only "Child 2" placeholders).
                    $childFree = in_array($group->service_type, self::CHILD_FREE_SERVICES, true);

                    $decoded = json_decode($group->children, true);

                    // The whole value is junk (e.g. the literal string "None").
                    if (! is_array($decoded)) {
                        $entriesRemoved++;
                        $groupsAffected++;

                        if (! $dryRun) {
                            $this->updateGroupChildren($group->id, []);
                        }

                        continue;
                    }

                    $kept = array_values(array_filter(
                        $decoded,
                        fn ($child) => ! $this->isJunkChildEntry($child, $childFree),
                    ));

                    $removed = count($decoded) - count($kept);

                    if ($removed === 0) {
                        continue;
                    }

                    $entriesRemoved += $removed;
                    $groupsAffected++;

                    if (! $dryRun) {
                        $this->updateGroupChildren($group->id, $kept);
                    }
                }
            });

        return [$groupsAffected, $entriesRemoved];
    }

    /**
     * Direct update to bypass the auto-repricing BookingGroup observer.
     *
     * @param  array<int, mixed>  $children
     */
    private function updateGroupChildren(int $groupId, array $children): void
    {
        DB::table('booking_groups')
            ->where('id', $groupId)
            ->update(['children' => json_encode($children)]);
    }

    private function isJunkChildEntry(mixed $child, bool $childFree = false): bool
    {
        if (! is_array($child)) {
            return true;
        }

        $hasBirthData = ! empty($child['birth_year'])
            || ! empty($child['birth_month'])
            || ! empty($child['birth_date']);

        // Keep anything with real birth data — even on a pet/companion booking a
        // dated child likely means the booking was mis-categorised, not junk.
        if ($hasBirthData) {
            return false;
        }

        // Pet/companion services shouldn't have children at all, so a birthless
        // entry is junk regardless of name (catches "Child 2" placeholders).
        if ($childFree) {
            return true;
        }

        return ImportUserService::isJunkChildName($child['name'] ?? null);
    }
}
