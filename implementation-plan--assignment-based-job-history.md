# Implementation Plan: Assignment-Based Job History

## Overview

### The Problem
The current "Engagement" tab on the admin caregiver profile shows a flat list of recent bookings. This is wrong because a **booking** (a job) and an **assignment** (a caregiver's relationship to a job) are not the same thing.

### The Concept
- **Booking** = a job that exists in the system (client books a service)
- **Assignment** = a caregiver's connection to that booking, with its own resolution state

This decoupling matters because a single booking can involve multiple caregivers:
- Caregiver A is assigned to job #2847 → backs out → assignment: `backed_out`
- Caregiver B is reassigned to job #2847 → completes it → assignment: `completed`
- The booking itself still belongs to whoever actually worked it

Both caregivers see job #2847 in their Job History, but with different resolutions. This is what makes reliability tracking accurate — back-outs belong to assignments, not bookings.

### Wireframe Reference
See `sitterwise_wireframe_v2.html` — section "Assignments View" (lines 1726–1839). The wireframe shows Ashlyn Tran's profile with the "Job History" tab active, displaying an assignment table with columns: Job#, Date, Client, Resolution, Notes. Resolution badges use color coding: green for Completed, red for Backed Out, amber for Excused, teal for Reassigned.

---

## Phase 1: Database Migration & Enum

### 1a. Create `AssignmentResolution` Enum

**File**: `app/Enums/AssignmentResolution.php`

```php
<?php

namespace App\Enums;

enum AssignmentResolution: string
{
    case Completed = 'completed';
    case BackedOut = 'backed_out';
    case BackedOutExcused = 'backed_out_excused';
    case Reassigned = 'reassigned';
    case NoShow = 'no_show';
    case CancelledBySitterwise = 'cancelled_by_sitterwise';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::BackedOut => 'Backed Out',
            self::BackedOutExcused => 'Backed Out (Excused)',
            self::Reassigned => 'Reassigned',
            self::NoShow => 'No-Show',
            self::CancelledBySitterwise => 'Cancelled by Sitterwise',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => '#22C55E',
            self::BackedOut => '#EF4444',
            self::BackedOutExcused => '#F59E0B',
            self::Reassigned => '#0EA5E9',
            self::NoShow => '#DC2626',
            self::CancelledBySitterwise => '#6B7280',
        };
    }
}
```

**Naming rules** (from existing enums):
- File: `{PascalCase}.php` (e.g., `CaregiverStatus.php`, `ServiceType.php`)
- Backed enum (`enum X: string`)
- Methods: `label(): string`, `color(): string`
- Same pattern as `CaregiverStatus` — `match ($this)` for each case

### 1b. Create `caregiver_assignments` Migration

**Command**: `php artisan make:migration create_caregiver_assignments_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->string('resolution')->nullable()
                ->comment('Values match AssignmentResolution enum: completed, backed_out, backed_out_excused, reassigned, no_show, cancelled_by_sitterwise');
            $table->timestamp('resolution_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->boolean('late_arrival_flag')->default(false);
            $table->text('late_arrival_note')->nullable();
            $table->foreignId('excused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('excused_at')->nullable();
            $table->timestamps();

            $table->unique(['caregiver_id', 'booking_id'], 'unique_assignment');
            $table->index('resolution');
            $table->index('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_assignments');
    }
};
```

**Key constraints**:
- `unique(['caregiver_id', 'booking_id'])` — a caregiver can only have one assignment per booking
- `cascadeOnDelete` for both FKs — if the booking or caregiver is deleted, the assignment goes too
- `resolution` is nullable — un-resolved assignments exist (e.g., a pending future job)

---

## Phase 2: Model & Relationships

### 2a. Create `CaregiverAssignment` Model

**Command**: `php artisan make:model CaregiverAssignment`

```php
<?php

namespace App\Models;

use App\Enums\AssignmentResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverAssignment extends Model
{
    protected $fillable = [
        'caregiver_id',
        'booking_id',
        'assigned_at',
        'resolution',
        'resolution_at',
        'resolution_note',
        'late_arrival_flag',
        'late_arrival_note',
        'excused_by',
        'excused_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'resolution_at' => 'datetime',
        'excused_at' => 'datetime',
        'late_arrival_flag' => 'boolean',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function excusedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excused_by');
    }

    public function resolve(AssignmentResolution $resolution, ?string $note = null): void
    {
        $this->update([
            'resolution' => $resolution->value,
            'resolution_at' => now(),
            'resolution_note' => $note ?? $this->resolution_note,
        ]);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolution');
    }

    public function scopeWithResolution($query, AssignmentResolution $resolution)
    {
        return $query->where('resolution', $resolution->value);
    }
}
```

**Model conventions** (from existing models):
- `$fillable` (not `$guarded`) — same as `Caregiver` model
- `$casts` as property — same as `Caregiver`
- Full return type hints on relationship methods: `BelongsTo`, `HasMany`, etc.
- Eloquent scope methods prefixed with `scope` — same as `Booking::scopeInFuture()`
- Use `now()` helper, not `Carbon::now()`

### 2b. Add Relationship to `Caregiver` Model

In `app/Models/Caregiver.php`, add:

```php
public function assignments(): HasMany
{
    return $this->hasMany(CaregiverAssignment::class);
}

public function assignedBookings(): BelongsToMany
{
    return $this->belongsToMany(Booking::class, 'caregiver_assignments')
        ->withPivot('resolution', 'assigned_at', 'resolution_at', 'resolution_note', 'late_arrival_flag')
        ->withTimestamps();
}
```

Import: `use Illuminate\Database\Eloquent\Relations\HasMany;`

### 2c. Add Relationship to `Booking` Model

In `app/Models/Booking.php`, add:

```php
public function assignments(): HasMany
{
    return $this->hasMany(CaregiverAssignment::class);
}
```

---

## Phase 3: Backfill Command

Create an Artisan command to backfill assignments for all existing completed/cancelled bookings.

**Command**: `php artisan make:command BackfillCaregiverAssignments`

```php
<?php

namespace App\Console\Commands;

use App\Enums\AssignmentResolution;
use App\Models\Booking;
use Illuminate\Console\Command;

class BackfillCaregiverAssignments extends Command
{
    protected $signature = 'app:backfill-assignments';
    protected $description = 'Create caregiver_assignments records for existing bookings';

    public function handle(): int
    {
        $bookings = Booking::whereNotNull('caregiver_id')
            ->whereNotNull('confirmed_at')
            ->with('caregiver')
            ->get();

        $count = 0;
        $bar = $this->output->createProgressBar($bookings->count());

        foreach ($bookings as $booking) {
            $resolution = match ($booking->status) {
                'cancelled' => AssignmentResolution::CancelledBySitterwise,
                default => AssignmentResolution::Completed,
            };

            $booking->assignments()->firstOrCreate(
                ['caregiver_id' => $booking->caregiver_id],
                [
                    'assigned_at' => $booking->confirmed_at ?? $booking->created_at,
                    'resolution' => $resolution->value,
                    'resolution_at' => $resolution === AssignmentResolution::Completed
                        ? ($booking->end_datetime ?? $booking->updated_at)
                        : $booking->updated_at,
                ],
            );

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled {$count} assignments.");

        return Command::SUCCESS;
    }
}
```

Run: `php artisan app:backfill-assignments`

**Note**: This command can be deleted after one-time use, or kept for future re-runs (uses `firstOrCreate` so it's idempotent).

---

## Phase 4: Booking Flow Integration

Assignments should be created and resolved automatically during the normal booking lifecycle.

### 4a. Create Assignment When Booking Is Confirmed

The booking confirmation flow already exists (handled elsewhere). When a booking is confirmed and a caregiver is assigned (i.e., `booking.caregiver_id` is set), create an assignment record. The best place is in the `Booking` model's `booted()` or in the controller that confirms bookings.

If using model events, add to `Boot` in `app/Models/Booking.php`:

```php
protected static function booted(): void
{
    static::saved(function (Booking $booking) {
        // When a caregiver is assigned to a booking, create an assignment
        if ($booking->wasChanged('caregiver_id') && $booking->caregiver_id) {
            $booking->assignments()->firstOrCreate(
                ['caregiver_id' => $booking->caregiver_id],
                ['assigned_at' => now()],
            );
        }
    });
}
```

**Important check**: `Booking` currently uses `static::creating()` and `static::updating()` in `boot()`, not `booted()`. Follow the existing pattern — either add to the existing `boot()` closures, or use `booted()` (both work). The existing `boot()` does calculations on `creating` and `updating`, so a `saved` event in `booted()` is the cleanest addition.

### 4b. Resolve Assignment on Checkout

When the checkout flow completes a booking (marks it as done), update the assignment resolution to `completed`.

Find the checkout controller (likely in `BookingCheckoutController` or similar). After checkout succeeds:

```php
$booking->assignments()
    ->where('caregiver_id', $booking->caregiver_id)
    ->first()
    ?->resolve(AssignmentResolution::Completed);
```

---

## Phase 5: Frontend — Job History Tab

### 5a. Rename and Reconfigure the Tab

In `resources/js/pages/admin/caregivers/show.tsx`:

1. Change `'engagement'` tab key/label/icon to `'job_history'` / `'Job History'` / `Briefcase`
2. Add `jobHistory` to the `Props` interface (lazy-loaded via Inertia defer)

### 5b. Add Deferred Prop to Controller

In `app/Http/Controllers/CaregiverController.php@show()`, replace the `recentJobs` deferred prop:

```php
'jobHistory' => Inertia::defer(fn () => CaregiverAssignment::with(['booking.client.user', 'booking.hotel'])
    ->where('caregiver_id', $caregiver->id)
    ->orderBy('assigned_at', 'desc')
    ->take(50)
    ->get()
    ->map(fn ($assignment) => [
        'id' => $assignment->id,
        'job_number' => $assignment->booking->ulid ?? '#'.$assignment->booking->id,
        'date' => $assignment->booking->start_datetime?->format('Y-m-d\TH:i:s'),
        'client_name' => $assignment->booking->client?->user?->name ?? '—',
        'client_description' => $assignment->booking->hotel?->name
            ?? $assignment->booking->address_city
            ?? '—',
        'resolution' => $assignment->resolution,
        'resolution_label' => $assignment->resolution
            ? AssignmentResolution::tryFrom($assignment->resolution)?->label()
            : 'Pending',
        'resolution_color' => $assignment->resolution
            ? AssignmentResolution::tryFrom($assignment->resolution)?->color()
            : '#6B7280',
        'resolution_note' => $assignment->resolution_note,
        'late_arrival' => $assignment->late_arrival_flag,
    ]),
),
```

Remove the old `recentJobs` deferred prop.

### 5c. Add `jobHistory` to the Frontend Interface

```typescript
interface JobAssignment {
    id: number;
    job_number: string;
    date: string;
    client_name: string;
    client_description: string;
    resolution: string | null;
    resolution_label: string;
    resolution_color: string;
    resolution_note: string | null;
    late_arrival: boolean;
}
```

### 5d. Add to Component Props

```typescript
interface Props {
    caregiver: Caregiver;
    statuses: Status[];
    reviews?: Review[];
    jobHistory?: JobAssignment[];
}
```

### 5e. Build the Job History Tab Panel

Replace the Engagement tab content with:

```tsx
{activeTab === 'job_history' && (
    <div className="border border-border bg-card p-6">
        <div className="mb-4 flex items-center justify-between">
            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">Job History</h2>
            <Link href={`/caregivers/${caregiver.id}/jobs`} className="text-sm text-primary hover:underline">
                View Full Job History
            </Link>
        </div>

        {jobHistory === undefined ? (
            /* Skeleton loader — match card dimensions */
            <div className="space-y-3">
                {[1, 2, 3, 4, 5].map((i) => (
                    <div key={i} className="animate-pulse space-y-2 rounded-lg border border-border p-4">
                        <div className="h-4 w-24 rounded bg-muted" />
                        <div className="h-3 w-full rounded bg-muted" />
                    </div>
                ))}
            </div>
        ) : jobHistory.length > 0 ? (
            <div className="-mx-6 -mb-6 mt-4 border-t border-border">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Job</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Client</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Resolution</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobHistory.map((row) => (
                                <tr key={row.id} className="border-b border-border transition hover:bg-blush last:border-0">
                                    <td className="px-4 py-3 text-sm whitespace-nowrap font-mono text-foreground">
                                        {row.job_number}
                                    </td>
                                    <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                        {formatDisplayDateTime(row.date)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="text-sm font-medium text-foreground">
                                            {row.client_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.client_description}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className="inline-block rounded px-2 py-0.5 text-[11px] font-semibold"
                                            style={{
                                                backgroundColor: row.resolution_color + '20',
                                                color: row.resolution_color,
                                            }}
                                        >
                                            {row.resolution_label}
                                        </span>
                                        {row.late_arrival && (
                                            <span className="ml-1 text-[10px] text-amber-600 font-medium">
                                                (Late)
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground max-w-[200px] truncate">
                                        {row.resolution_note || '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        ) : (
            <p className="text-sm text-muted-foreground">No job history yet.</p>
        )}
    </div>
)}
```

Note: The "View Full Job History" link points to the existing `/caregivers/{id}/jobs` page (which shows all bookings). This can later be updated to show all assignments instead.

**Styling rules** (from existing codebase patterns):
- Card: `border border-border bg-card p-6`
- Heading: `mb-4 font-serif text-lg font-semibold text-foreground`
- Table header: `bg-foreground` with white `text-[11px] font-semibold tracking-wider uppercase`
- Table cell: `px-4 py-3 text-sm whitespace-nowrap text-foreground`
- Row hover: `hover:bg-blush`
- Date formatting: `formatDisplayDateTime()` from `@/lib/datetime` (outputs "May 31, 2026, 9:15 AM")
- Resolution badge: inline-block with color applied as bg+text via hex `color` + `color + '20'` for background

### 5f. Remove the Old Engagement Tab

Delete the entire `{activeTab === 'engagement' && (...)}` block. Remove the `recentJobs` prop from `Props` interface and the `usePage` destructuring.

### 5g. Update the TABS Array

```typescript
const TABS = [
    { key: 'summary', label: 'Summary', icon: Sun },
    { key: 'application', label: 'Application', icon: FileText },
    { key: 'references', label: 'References', icon: Users },
    { key: 'reviews', label: 'Reviews', icon: Star },
    { key: 'internal_rating', label: 'Internal Rating', icon: ClipboardCheck },
    { key: 'job_history', label: 'Job History', icon: Briefcase },
    { key: 'compliance', label: 'Compliance', icon: Shield },
    { key: 'notes', label: 'Notes', icon: MessageSquare },
] as const;
```

---

## Phase 6: Back-Out & Resolution Admin Actions

This is the Low #3 item from the priority doc. Build after the core table is working.

### 6a. Admin Routes

In `routes/web.php`:

```php
Route::post('assignments/{assignment}/resolve', [CaregiverAssignmentController::class, 'resolve'])->name('assignments.resolve');
Route::post('assignments/{assignment}/excuse', [CaregiverAssignmentController::class, 'excuse'])->name('assignments.excuse');
Route::post('assignments/{assignment}/flag-late', [CaregiverAssignmentController::class, 'flagLateArrival'])->name('assignments.flag-late');
```

### 6b. Admin Controller

`php artisan make:controller CaregiverAssignmentController`

With methods:
- `resolve(Request, CaregiverAssignment)` — accepts `resolution` (string from enum), optional `note`
- `excuse(Request, CaregiverAssignment)` — marks as `backed_out_excused`, records `excused_by` and `excused_at`
- `flagLateArrival(Request, CaregiverAssignment)` — toggles `late_arrival_flag`, records note

### 6c. Action Buttons Under the Table

Per the wireframe (line 1830–1834), add action buttons:

```tsx
<div className="mt-4 flex flex-wrap gap-2 border-t border-border pt-4">
    <Button variant="outline" size="sm">Mark a back-out as Excused</Button>
    <Button variant="outline" size="sm">Log a no-show</Button>
    <Button variant="outline" size="sm">Log a late arrival</Button>
</div>
```

Each button opens a small dialog or slide-over (use the existing `Sheet` component pattern from the codebase).

---

## Key Codebase Conventions (For the Developer)

### PHP
- **Constructor property promotion**: `public function __construct(public ServiceType $service) {}`
- **Return types**: Always explicit — `public function label(): string`
- **Enums**: `string` backed, with `label(): string` and `color(): string` methods
- **Models**: `$fillable` (not `$guarded`), `$casts` as property
- **Form Requests**: Separate class per action, e.g., `StoreXRequest`, `UpdateXRequest`
- **Migrations**: `YYYY_MM_DD_HHMMSS_create_{table}_table.php`
- **Relationships**: Full return type hints (`BelongsTo`, `HasMany`, `BelongsToMany`)

### Frontend (Inertia v2 + React)
- **Tab state**: Client-side `useState<string>` with `activeTab` — no URL routing for tabs
- **Deferred props**: Use `Inertia::defer()` for lazy-loaded data (Reviews, Job History)
- **Loading state**: Check `reviews === undefined` for deferred data, show `<div className="animate-pulse ...">` skeleton
- **File naming**: Pages in `resources/js/pages/admin/`
- **Date formatting**: `formatDisplayDateTime()` from `@/lib/datetime` — outputs "May 31, 2026, 9:15 AM"
- **Service labels**: Use `ServiceType::tryFrom($x)?->label() ?? $x` on the backend, pass as pre-formatted strings
- **Components**: Use existing UI components (`Button`, `Select`, `Sheet`, `Spinner`, `Badge`) from `@/components/ui/*`
- **Lucide icons**: Import from `lucide-react` (e.g., `Briefcase`, `FileText`, `Users`)

### Testing
- **Pest PHP**: Tests in `tests/Feature/` and `tests/Unit/`
- **Run tests**: `php artisan test --compact`
- **Filter**: `php artisan test --compact --filter=TestName`
- **Factories**: Use existing model factories, custom states available

---

## Summary of Files to Create/Modify

| Action | File | Description |
|--------|------|-------------|
| **CREATE** | `app/Enums/AssignmentResolution.php` | Enum with labels/colors |
| **CREATE** | `database/migrations/XXXX_XX_XX_create_caregiver_assignments_table.php` | New table |
| **CREATE** | `app/Models/CaregiverAssignment.php` | New model |
| **CREATE** | `app/Console/Commands/BackfillCaregiverAssignments.php` | One-time backfill |
| **CREATE** | `app/Http/Controllers/CaregiverAssignmentController.php` | Admin actions (Phase 6) |
| **MODIFY** | `app/Models/Caregiver.php` | Add `assignments()` and `assignedBookings()` relationships |
| **MODIFY** | `app/Models/Booking.php` | Add `assignments()` relationship, add `booted()` saved event |
| **MODIFY** | `app/Http/Controllers/CaregiverController.php` | Replace `recentJobs` with `jobHistory` deferred prop |
| **MODIFY** | `app/Http/Controllers/CheckoutController.php` (or wherever checkout lives) | Resolve assignment to `completed` |
| **MODIFY** | `resources/js/pages/admin/caregivers/show.tsx` | Replace Engagement tab with Job History, update TABS, interfaces, props |
| **MODIFY** | `routes/web.php` | Add assignment-related routes (Phase 6) |
| **DELETE** | N/A | Old Engagement tab content removed from show.tsx |

## Order of Implementation

1. Enum → Migration → Model (Phase 1–2)
2. Run `php artisan migrate`
3. Backfill command → run it (Phase 3)
4. Booking model event → checkout integration (Phase 4)
5. Frontend tab (Phase 5)
6. Admin resolution actions (Phase 6, separate)
