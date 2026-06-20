# Client Booking Fees Display

## Overview

The Fees section on the client booking detail page (`client/bookings/show.tsx`) has three display states depending on whether pricing has been applied to the booking.

## Three States

| State | `charge_to_client_hourly` | `charge_to_client` | What the client sees |
|---|---|---|---|
| **Priced** | not null (e.g. 40.00) | correctly calculated (e.g. 160.00) | Hourly Rate: $40.00/hr, Hours: 4.0, Total: $160.00 |
| **Pending** | null | 0.00 | Hours: 4.0, Total: To be calculated |
| **Comped** | 0.00 | 0.00 | Hourly Rate: $0.00/hr, Hours: 4.0, Total: $0.00 |

The distinguishing condition (`pricingNotSet`):

```
charge_to_client_hourly === null && charge_to_client === 0
```

- **Priced**: hourly is not null → `pricingNotSet = false` → show real numbers.
- **Pending**: hourly is null AND total is 0 → `pricingNotSet = true` → show "To be calculated".
- **Comped**: hourly is `0.00` (not null) → `pricingNotSet = false` → show $0.00 intentionally.

## Backend: How `charge_to_client_hourly` Gets Set

### On creation (`Booking::creating` boot event)

```php
static::creating(function (Booking $booking) {
    $booking->calculateTotalWorkingHours();
    $booking->calculateHourlyRate();      // sets charge_to_client_hourly
    $booking->calculateTotalAmount();     // uses charge_to_client_hourly
});
```

`calculateHourlyRate()` looks up the `PricingRule` matching the booking group's `service_type` and child count. If a rule exists, `charge_to_client_hourly` is set from it. If no rule exists, it's set to `null` (not `0.00`), so the frontend can distinguish "not priced yet" from "priced at $0" (comped).

### On group update (`BookingGroupObserver@updated`)

When `service_type`, `children`, or `pets` change on a booking group, the observer re-prices all non-finalized bookings (not completed/paid/cancelled, with a future end date).

## Frontend: Display Logic

In `resources/js/pages/client/bookings/show.tsx`:

```typescript
const pricingNotSet = booking.charge_to_client_hourly === null &&
    booking.charge_to_client === 0;
```

The Total line displays:
- `pricingNotSet === true` → **"To be calculated"** (italic, muted)
- `pricingNotSet === false` → formatted currency (e.g. `$160.00`)

The hourly rate line only renders when `charge_to_client_hourly != null` (already existing behavior, unchanged).

## Visual Examples

### Pending pricing (newly created booking)

```
Fees
  Hours          4.0
  Total          To be calculated
```

### Priced booking

```
Fees
  Hourly Rate    $40.00/hr
  Hours          4.0
  Total          $160.00
```

### Comped booking

```
Fees
  Hourly Rate    $0.00/hr
  Hours          4.0
  Total          $0.00
```
