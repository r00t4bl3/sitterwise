# Rating System Implementation Plan (V3 - Polymorphic)

## Overview

Implement a reciprocal rating system where:

1. **Clients** can rate caregivers after job completion (1-5 stars)
2. **Caregivers** can rate clients after job completion (1-5 stars)
3. **Admins** can rate caregivers globally (separate from job-specific feedback)

Ratings are stored in a multi-row table using **polymorphic relationships** for flexibility and clean Laravel relationships.

---

## Requirements

- Rating scale: **1-5 stars** (whole and half stars) - UI supports 0.5 increments
- Timing: **After job completion** (Caregivers during checkout, Clients via dashboard)
- Checkout rating: **Optional** but prominently visible
- Recalculation: Cumulative ratings are cached on the respective models:
    - `caregivers.rating` (Average of ratings from clients)
    - `clients.rating` (Average of ratings from caregivers)
- Admin rating: **Global field** on the caregiver model → `caregiver.admin_rating`
- Auditability: **Soft deletes** enabled on ratings to allow voiding without data loss.

---

## 1. Database Changes

### Table: `booking_ratings`

Uses polymorphic relationship to track what model is being rated.

| Column         | Type         | Constraints                 | Description                                      |
| -------------- | ------------ | --------------------------- | ------------------------------------------------ |
| `id`           | bigint       | PRIMARY KEY, AUTO_INCREMENT |                                                  |
| `booking_id`   | bigint       | FOREIGN KEY (bookings.id)   | Reference to booking (context)                   |
| `rater_id`     | bigint       | FOREIGN KEY (users.id)      | User who provided the rating                     |
| `ratable_id`   | bigint       | NOT NULL                    | ID of the rated entity (caregiver_id or client_id)|
| `ratable_type` | varchar(255) | NOT NULL                    | 'App\Models\Caregiver' or 'App\Models\Client'   |
| `rating`       | decimal(3,2) | NOT NULL                    | 1-5 rating value                                 |
| `comment`      | text         | NULLABLE                    | Optional feedback comment                        |
| `created_at`   | timestamp    |                             |                                                  |
| `updated_at`   | timestamp    |                             |                                                  |
| `deleted_at`   | timestamp    | NULLABLE                    | Soft delete flag for voiding ratings             |

**Indexes:**

- **Unique**: `booking_id` + `rater_id` + `ratable_id` + `ratable_type` (prevents double-rating the same entity for a job)
- **Index**: `ratable_id` + `ratable_type` (for fast recalculation of averages)

### Migration: `caregivers` table

Add new field for global admin rating:

```sql
ALTER TABLE caregivers ADD COLUMN admin_rating decimal(3,2) NULL AFTER rating;
```

### Migration: `clients` table

Add field for client reputation score:

```sql
ALTER TABLE clients ADD COLUMN rating decimal(3,2) DEFAULT 0 AFTER stripe_customer_id;
```

---

## 2. Backend Implementation

### Model: `app/Models/BookingRating.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingRating extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'rater_id',
        'ratable_id',
        'ratable_type',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

### Model: `app/Models/Booking.php`

Add polymorphic relationships:

```php
public function ratings(): MorphMany
{
    return $this->morphMany(BookingRating::class, 'ratable');
}

public function clientRating(): MorphOne
{
    return $this->morphOne(BookingRating::class, 'ratable')
        ->where('ratable_type', Client::class);
}

public function caregiverRating(): MorphOne
{
    return $this->morphOne(BookingRating::class, 'ratable')
        ->where('ratable_type', Caregiver::class);
}
```

### Model: `app/Models/Caregiver.php`

```php
public function ratings(): MorphMany
{
    return $this->morphMany(BookingRating::class, 'ratable');
}

public function receivedRatings(): MorphMany
{
    return $this->morphMany(BookingRating::class, 'ratable');
}

public function recalculateRating(): void
{
    $average = $this->ratings()
        ->whereNull('deleted_at')
        ->avg('rating') ?: 0;

    $this->update(['rating' => round($average, 2)]);
}
```

### Model: `app/Models/Client.php`

```php
public function ratings(): MorphMany
{
    return $this->morphMany(BookingRating::class, 'ratable');
}

public function receivedRatings(): MorphMany
{
    return $this->morphMany(BookingRating::class, 'ratable');
}

public function recalculateRating(): void
{
    $average = $this->ratings()
        ->whereNull('deleted_at')
        ->avg('rating') ?: 0;

    $this->update(['rating' => round($average, 2)]);
}
```

### Admin Rating (Dedicated Audit-Ready Endpoint)

```php
// routes/web.php or routes/api.php
Route::put('/admin/caregivers/{caregiver}/admin-rating', [AdminCaregiverController::class, 'updateAdminRating'])
    ->middleware(['auth', 'admin'])
    ->name('admin.caregivers.update-admin-rating');
```

---

## 3. Creating Ratings

When a caregiver rates a client (caregiver → client):

```php
$booking->ratings()->create([
    'rater_id' => $caregiver->user_id,
    'ratable_type' => Client::class,
    'ratable_id' => $client->id,
    'rating' => 4.5,
    'comment' => 'Great client!',
]);

// Recalculate client's average rating
$client->recalculateRating();
```

When a client rates a caregiver (client → caregiver):

```php
$booking->ratings()->create([
    'rater_id' => $client->user_id,
    'ratable_type' => Caregiver::class,
    'ratable_id' => $caregiver->id,
    'rating' => 5.0,
    'comment' => 'Wonderful caregiver!',
]);

// Recalculate caregiver's average rating
$caregiver->recalculateRating();
```

---

## 4. Frontend Implementation

### Caregiver View (Jobs Index/Checkout)
- **Integration**: The rating fields (`rating`, `comment`) should be submitted along with the checkout/completion request.
- **Validation**: Rules should be `nullable` since it is an optional step.

### Client View (Booking Details)
- **Component**: A dedicated `RatingForm` that appears once the booking `status` is `completed`.

---

## 5. Testing Plan

### Unit Tests
- Verify polymorphic relationships work correctly
- Verify `recalculateRating()` ignores soft-deleted entries
- Test that the model cast handles various inputs (e.g., `4.5` stays `4.5`)

### Feature Tests
- **Unique Constraint**: Attempting a second rating for the same booking/rater/ratable should be handled (either blocked or updated)
- **Security**: Ensure a Caregiver cannot rate themselves or a client from a job they weren't assigned to
- **Concurrency**: Test behavior when multiple rating submissions are triggered simultaneously
- **Polymorphic**: Verify ratings correctly associate with Caregiver vs Client models

---

## Answers (Finalized)

1. Rating step in checkout: **Optional** but prominent. ✓
2. Support for half-stars: **Yes**, supports 0.5 increments. ✓
3. Soft-deletes: **Yes**, for auditability. ✓
4. Admin Score: **Dedicated audited endpoint**. ✓

---

## Why Polymorphic?

The polymorphic approach was chosen because:

1. **Clean Laravel relationships** - No subqueries or workarounds
2. **Extensible** - Adding new ratable models (e.g., Sitter, Admin) requires no schema changes
3. **Direction implicit** - Which model is `ratable` already tells you the direction (caregiver rated = client gave rating)
4. **Easy calculations** - `$caregiver->ratings()->avg('rating')` works naturally

---

## Migration Notes (V2 → V3)

The migration renamed columns:
- `ratee_id` → `ratable_id`
- `type` → `ratable_type`

Data migration mapping:
- `type = 'caregiver_to_client'` → `ratable_type = 'App\Models\Client'` (client was rated)
- `type = 'client_to_caregiver'` → `ratable_type = 'App\Models\Caregiver'` (caregiver was rated)