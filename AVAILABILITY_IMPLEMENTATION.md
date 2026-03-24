# Availability Management Implementation Guide

## Recommended Approach: Single Controller with Policy

### Architecture

```
AvailabilityController (Single Controller)
    └── AvailabilityPolicy (Role-based Authorization)
```

### Database Schema

```php
// Migration: create_availabilities_table
Schema::create('availabilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
    $table->date('date');
    $table->json('time_slots'); // ['morning', 'afternoon', 'evening'] or time ranges
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['caregiver_id', 'date']);
    $table->index('date');
});
```

### Model: app/Models/Availability.php

```php
class Availability extends Model
{
    protected $fillable = ['caregiver_id', 'date', 'time_slots', 'notes'];
    protected $casts = ['date' => 'date', 'time_slots' => 'array'];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
```

### Controller: app/Http/Controllers/AvailabilityController.php

```php
class AvailabilityController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Availability::class);
        // Return availabilities based on role
    }

    public function store(Request $request)
    {
        $this->authorize('create', Availability::class);
        // Validate and create
    }

    public function update(Request $request, Availability $availability)
    {
        $this->authorize('update', $availability);
        // Validate and update
    }

    public function destroy(Availability $availability)
    {
        $this->authorize('delete', $availability);
        // Delete
    }
}
```

### Policy: app/Policies/AvailabilityPolicy.php

```php
class AvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'caregiver', 'client']);
    }

    public function view(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'caregiver']);
    }

    public function update(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }

    public function delete(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }
}
```

### Authorization Rules by Role

| Action                  | Admin | Caregiver | Client         |
| ----------------------- | ----- | --------- | -------------- |
| View all availabilities | ✅    | Own only  | ✅ (read-only) |
| Create availability     | ✅    | Own only  | ❌             |
| Update availability     | ✅    | Own only  | ❌             |
| Delete availability     | ✅    | Own only  | ❌             |

### Route Definition

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('availabilities', AvailabilityController::class);
});
```

### Frontend Integration

Add to sidebar conditionally based on user role:

```jsx
// In AppSidebar
const { user } = usePage().props;

const caregiverNavItems = [
    { title: 'My Availability', href: '/availabilities', icon: Calendar },
];

const adminNavItems = [
    { title: 'Manage Availability', href: '/availabilities', icon: Calendar },
];

const navItems =
    user.role === 'admin'
        ? [...mainNavItems, ...adminNavItems]
        : user.role === 'caregiver'
          ? [...mainNavItems, ...caregiverNavItems]
          : mainNavItems;
```

---

## Notes

- Caregiver availability can be imported from external availability app later
- Time slots can be stored as JSON: `["morning", "afternoon", "evening"]` or as time ranges
- Consider adding booking functionality for clients to book time slots
