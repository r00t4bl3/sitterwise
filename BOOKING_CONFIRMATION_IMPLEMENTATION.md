# Booking Confirmation Implementation Guide

---

# 🧠 High-Level Design (what you’re building)

You are implementing:

- **Optimistic access (view freely)**
- **Pessimistic claim (reserve on Accept)**
- **Atomic confirmation (final step)**
- **Auto-expiration**
- **Real-time updates (WebSockets)**

---

# 🗄️ 1. Database Design

## bookings table (core fields)

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->enum('status', ['open', 'reserved', 'confirmed'])->default('open');

    $table->unsignedBigInteger('reserved_by')->nullable();
    $table->timestamp('reservation_expires_at')->nullable();

    $table->unsignedBigInteger('confirmed_by')->nullable();

    $table->timestamp('confirmed_at')->nullable();
});
```

---

## 🔑 Important constraints (don’t skip this)

- Only ONE reservation allowed at a time
- Only ONE confirmed caregiver

You enforce this **in logic (atomic queries)**, not just DB constraints.

---

# 🔄 2. State Machine

```
OPEN → RESERVED → CONFIRMED
        ↓
     (expired → back to OPEN)
```

---

# 🌐 3. API Endpoints

## 1. Reserve Job (called when clicking “Accept”)

**POST** `/api/bookings/{id}/reserve`

### Controller logic:

```php
public function reserve($id)
{
    $caregiverId = auth()->id();

    $updated = DB::update("
        UPDATE bookings
        SET reserved_by = ?,
            reservation_expires_at = DATE_ADD(NOW(), INTERVAL 1 MINUTE),
            status = 'reserved'
        WHERE id = ?
          AND status = 'open'
          AND reserved_by IS NULL
    ", [$caregiverId, $id]);

    if ($updated === 0) {
        return response()->json([
            'success' => false,
            'message' => 'Job already taken'
        ], 409);
    }

    // Broadcast event here
    broadcast(new JobReserved($id))->toOthers();

    return response()->json([
        'success' => true,
        'expires_in' => 60
    ]);
}
```

---

## 2. Confirm Job (inside popup)

**POST** `/api/bookings/{id}/confirm`

```php
public function confirm($id)
{
    $caregiverId = auth()->id();

    $updated = DB::update("
        UPDATE bookings
        SET status = 'confirmed',
            confirmed_by = ?,
            confirmed_at = NOW()
        WHERE id = ?
          AND reserved_by = ?
          AND reservation_expires_at > NOW()
    ", [$caregiverId, $id, $caregiverId]);

    if ($updated === 0) {
        return response()->json([
            'success' => false,
            'message' => 'Reservation expired or invalid'
        ], 409);
    }

    broadcast(new JobConfirmed($id))->toOthers();

    return response()->json(['success' => true]);
}
```

---

## 3. Optional: Release Reservation (cancel button)

```php
public function release($id)
{
    $caregiverId = auth()->id();

    DB::update("
        UPDATE bookings
        SET reserved_by = NULL,
            reservation_expires_at = NULL,
            status = 'open'
        WHERE id = ?
          AND reserved_by = ?
    ", [$id, $caregiverId]);

    broadcast(new JobReleased($id))->toOthers();

    return response()->json(['success' => true]);
}
```

---

# ⏱️ 4. Expiration Handling (VERY IMPORTANT)

## Option A (recommended): Lazy expiration

Whenever booking is fetched:

```php
if ($booking->reservation_expires_at && now()->gt($booking->reservation_expires_at)) {
    $booking->update([
        'reserved_by' => null,
        'reservation_expires_at' => null,
        'status' => 'open'
    ]);
}
```

---

## Option B: Scheduled job

In `app/Console/Kernel.php`:

```php
$schedule->call(function () {
    DB::table('bookings')
        ->where('status', 'reserved')
        ->where('reservation_expires_at', '<', now())
        ->update([
            'reserved_by' => null,
            'reservation_expires_at' => null,
            'status' => 'open'
        ]);
})->everyMinute();
```

---

# 📡 5. WebSocket Events (Laravel Echo / Pusher)

## Events to create:

- `JobReserved`
- `JobConfirmed`
- `JobReleased`

---

## Example Event

```php
class JobReserved implements ShouldBroadcast
{
    public $bookingId;

    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function broadcastOn()
    {
        return new Channel('booking.' . $this->bookingId);
    }
}
```

---

## Frontend behavior

When receiving:

### `JobReserved`

- Disable Accept button
- Show: “Another caregiver is reviewing this job”

### `JobConfirmed`

- Disable everything
- Show: “Job has been taken”

---

# 🎯 6. Frontend Flow (exact behavior)

## On job page:

### Click "Accept"

- Call `/reserve`
- If success → open popup + countdown
- If fail → show error

---

## In popup:

- Show countdown timer (1 min)
- Button: Confirm

---

## On Confirm:

- Call `/confirm`
- Success → redirect / success page
- Fail → show “expired or taken”

---

# ⚠️ Critical Edge Cases (make sure dev handles these)

1. **User opens multiple tabs**
    - Only one reserve will succeed

2. **User clicks confirm after expiry**
    - Must fail safely

3. **Network delay**
    - Backend still guarantees correctness

4. **User closes popup**
    - Reservation expires automatically

---

# 🚀 Optional Improvements

## 1. Priority rollout

- Notify 2 caregivers first
- Then expand after 60s

## 2. Retry mechanism

- If reservation expires → notify others again

## 3. Audit log

Track:

- who reserved
- who confirmed
- timestamps

---

# ✅ What your developer must NOT do

❌ Do NOT rely on frontend state
❌ Do NOT check availability before update (race condition)
❌ Do NOT use separate SELECT then UPDATE

👉 Always use **single atomic UPDATE**

---

# 🧾 Summary you can paste to your dev

> Implement a reservation system where:
>
> - “Accept” triggers atomic reservation (1 min TTL)
> - Only one caregiver can reserve
> - “Confirm” finalizes only if still reserved
> - Expired reservations return to open
> - WebSockets update all caregivers in real time

---

# Flowchart

```
+-----------------------------+
| Caregiver opens job page    |
| (no lock yet)               |
+-------------+---------------+
              |
              v
+-----------------------------+
| Click "Accept"              |
+-------------+---------------+
              |
              v
+-----------------------------+
| Backend: Try RESERVE job    |
| (atomic update)             |
+-------------+---------------+
              |
        +-----+-----+
        |           |
        v           v
+----------------+  +------------------------------+
| Reservation OK |  | Reservation FAILED           |
+--------+-------+  | (already taken by someone)   |
         |          +--------------+---------------+
         |                         |
         v                         v
+-----------------------------+   +----------------------+
| Show popup + countdown      |   | Show "Job taken"     |
| (e.g. 1 minutes)            |   | Disable actions      |
+-------------+---------------+   +----------------------+
              |
              v
+-----------------------------+
| Click "Confirm"             |
+-------------+---------------+
              |
              v
+-----------------------------+
| Backend: FINAL CONFIRM      |
| (only if still reserved)    |
+-------------+---------------+
              |
        +-----+-----+
        |           |
        v           v
+----------------+  +------------------------------+
| Confirm OK     |  | Confirm FAILED               |
| Job assigned   |  | (expired or taken)           |
+----------------+  +------------------------------+
```
