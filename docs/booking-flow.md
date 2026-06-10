# Booking Flow

## Status Lifecycle

```mermaid
stateDiagram-v2
    [*] --> received : Booking created
    received --> reserved : Caregiver reserves (60s TTL)
    reserved --> received : TTL expires / Caregiver releases
    reserved --> confirmed : Caregiver confirms within TTL
    confirmed --> completed : Caregiver checks out
    completed --> paid : Admin processes payment
    received --> cancelled : Cancel
    reserved --> cancelled : Cancel
    confirmed --> cancelled : Cancel
    paid --> [*]
    cancelled --> [*]
```

## Creation Channels

```mermaid
flowchart TD
    Entry([Booking Request]) --> G1
    Entry --> C1
    Entry --> A1

    subgraph Guest["Guest Booking"]
        direction TB
        G1[Guest submits form] --> G2[Validate & store in session]
        G2 --> G3[Stripe SetupIntent - card on file]
        G3 --> G4{New user?}
        G4 -->|Yes| G5[Create User + Client<br/>temp password]
        G4 -->|No| G6[Find existing User]
        G5 --> G7[Create BookingGroup<br/>submission_type = guest]
        G6 --> G7
        G7 --> G8[Create Booking/s<br/>status = received]
        G8 --> G9[Fire GuestAccountSetup<br/>send password setup email]
    end

    subgraph Client["Client Booking"]
        direction TB
        C1[Client submits form] --> C2[Load existing Client profile]
        C2 --> C3[Create BookingGroup<br/>submission_type = client]
        C3 --> C4[Create Booking/s<br/>status = received]
    end

    subgraph Admin["Admin Booking"]
        direction TB
        A1[Admin submits form] --> A2{New client?}
        A2 -->|Yes| A3[Create User + Client inline]
        A2 -->|No| A4[Use existing Client]
        A3 --> A5[Create BookingGroup]
        A4 --> A5
        A5 --> A6{Assign caregiver?}
        A6 -->|Yes| A7[Set caregiver_id directly<br/>Fire BookingAccepted]
        A6 -->|No| A8[Leave unassigned]
        A7 --> A9[Create Booking/s<br/>admin sets status]
        A8 --> A9
    end

    G9 --> Received([status = received])
    C4 --> Received
    A9 --> Received
```

## Caregiver Assignment

```mermaid
sequenceDiagram
    participant Admin
    participant System
    participant Caregiver

    Admin->>System: Select caregivers & notify
    System->>System: Create BookingCaregiverNotification records
    System->>Caregiver: Send email/SMS invitation

    Caregiver->>System: Click "Reserve"
    System->>System: Atomic DB update<br/>reserved_by = caregiver<br/>reservation_expires_at = now + 60s<br/>status = reserved
    System-->>Caregiver: Broadcast JobReserved

    alt Caregiver confirms within 60s
        Caregiver->>System: Click "Confirm"
        System->>System: Atomic DB update<br/>caregiver_id, confirmed_by, confirmed_at<br/>status = confirmed
        System->>System: Create CaregiverAssignment
        System->>System: Reserve availability slots<br/>(BookingAvailabilitySlot)
        System-->>Caregiver: Fire BookingAccepted (confirmation email)
    else TTL expires or caregiver releases
        System->>System: Revert to status = received<br/>Clear reserved_by
    end

    Note over Caregiver,System: Post-confirmation: caregiver can back out

    opt Caregiver backs out of confirmed booking
        Caregiver->>System: POST assignments/{id}/back-out<br/>with reason
        System->>System: CaregiverAssignment<br/>resolution = backed_out
        System->>Admin: Email notification<br/>(AdminCaregiverBackedOutMail)
        Note over System: Booking status unchanged → caregiver_id unchanged<br/>Availability slots NOT released<br/>Admin handles reassignment manually
    end
```

**Admin assignment** (no caregiver self-service): Admin sets `caregiver_id` directly on the booking → `Booking::saved` hook fires → `AvailabilityReservationService::reserve()` is called automatically.

**Unassign / Cancel:** Setting `caregiver_id = null` or `status = cancelled` → saved hook fires `AvailabilityReservationService::release()` → all `BookingAvailabilitySlot` records for that booking are deleted.

## Recommendation Pipeline

Before a caregiver is ever notified or assigned, the system scores and ranks all eligible caregivers. This happens in `CaregiverRecommendationService::getRecommendedCaregivers()`.

```mermaid
flowchart TD
    REQ([Recommendation Request<br/>Client + Booking + Date Ranges]) --> L1

    subgraph Setup["Setup & Filters"]
        L1[Load active caregivers<br/>filter: not blocked, not paused,<br/>has availability] --> L2
        L2[Load previous work IDs<br/>with this client] --> L3
        L3[Load recent work IDs<br/>3mo and 6mo buckets] --> L4
        L4[Load existing confirmed/received<br/>bookings for buffer check<br/>1 batched query all caregivers]
    end

    L4 --> LOOP

    subgraph LOOP["Per-Caregiver Scoring"]
        direction TB
        C1[Compute slot availability<br/>TimeSlotHelper → required slots<br/>− subtract used BookingAvailabilitySlots<br/>= free slots?] --> C2
        C2[Check buffer time<br/>gap against existing bookings<br/>respects CAREGIVER_BUFFER_MINUTES?] --> C3
        C3[Compute attributes<br/>specialty, location, favorited,<br/>previous work, recent work] --> C4
        C4[Compute weighted score<br/>sum of matched attribute weights]
    end

    LOOP --> SORT

    subgraph SORT["Results"]
        SR[Sort by score desc, name asc] --> TAKE
        TAKE[Take top N default 20] --> ICONS
        ICONS[Derive matchIcons<br/>from matched attributes] --> DONE
    end

    DONE([Return scored caregivers<br/>with matchIcons])
```

### Score Weights

Each criterion has a numeric weight. A caregiver's **score** = sum of weights for all matched criteria. Weights are designed so no combination of lower-priority criteria can outrank a higher-priority one.

| Priority | Criteria | Weight | Notes |
|---|---|---|---|
| 1 | Available + Favorited (bonus) | 100000 | Requires both — always outranks everything else |
| 2 | Available | 10000 | Slot check + buffer check both pass |
| 3 | Specialty match | 1000 | Matches service type age-group or sitter preference |
| 4 | Preferred location | 100 | Booking area is caregiver's preferred location |
| 5 | Willing location | 10 | Booking area is caregiver's non-preferred location |
| 6 | Recent work (3mo) | 3 | Completed work for any client in last 3 months |
| 7 | Previous work with client | 2 | Worked for this specific client before |
| 8 | Recent work (6mo) | 1 | Completed work for any client in last 6 months |

Score calculation:
```
score =
    (available && isFavorited ? 100000 : 0)
    + (available ? 10000 : 0)
    + (specialty ? 1000 : 0)
    + (preferredLocation ? 100 : 0)
    + (willingLocation ? 10 : 0)
    + (recentWork3mo ? 3 : 0)
    + (previousWork ? 2 : 0)
    + (recentWork6mo ? 1 : 0)
```

### Match Icons

Derived directly from matched attributes (not from score range). The frontend displays these icons to give transparency into why each caregiver was ranked how they were:

| Icon | Attribute | Shown when |
|---|---|---|
| `favorited` | Favorited by client | Caregiver is in client's favorites |
| `available` | Available | Slot check + buffer check both pass |
| `specialty` | Specialty match | Matches service type age-group or sitter preference (EAV) |
| `location_preferred` | Preferred location | Booking area is caregiver's preferred location |
| `location_willing` | Willing location | Booking area is caregiver's non-preferred location |
| `recent_work` | Recent work | Any work in last 6 months |
| `previous_work` | Previous work | Worked for this specific client before |

## Post-Confirmation Lifecycle

```mermaid
flowchart TD
    START(["Confirmed Booking"]) --> CO
    START --> BO
    START --> CA

    subgraph CO["Caregiver Checkout"]
        direction TB
        CO1[Caregiver submits checkout] --> CO2[Update actual times<br/>reimbursement, bonus]
        CO2 --> CO3[status = completed<br/>checkout_at set]
    end

    subgraph Payment["Admin Payment"]
        direction TB
        P1[Admin reviews & adjusts] --> P2[Process Payment]
        P2 --> P3[Stripe PaymentIntent<br/>off-session charge]
        P3 --> P4{Success?}
        P4 -->|Yes| P5[status = paid<br/>payment_status = charged<br/>Send receipt email]
        P4 -->|No| P6[Increment charge_attempt_count<br/>payment_status = failed]
        P6 --> P7[Auto-retry queue<br/>0s → 1h → 1d → 3d<br/>max 4 attempts]
    end

    subgraph Rating["Rating"]
        direction TB
        R1[Client rates caregiver] --> R2[Update aggregate rating]
        R3[Caregiver rates client] --> R4[Update aggregate rating]
    end

    subgraph CA["Admin Cancellation"]
        direction TB
        CA1[Admin clicks Cancel Booking<br/>with reason] --> CA2["POST /bookings/{booking}/cancel"]
        CA2 --> CA3[status = cancelled<br/>cancelled_at, reason, cancelled_by]
        CA3 --> CA3b[Release availability slots<br/>BookingAvailabilitySlot deleted]
        CA3b --> CA4[Zero all financial amounts]
        CA4 --> CA5[Resolve unresolved assignment<br/>to CancelledBySitterwise]
    end

    subgraph BO["Caregiver Back-Out"]
        direction TB
        BO1[Caregiver submits back-out<br/>with reason] --> BO2["POST /assignments/{id}/back-out"]
        BO2 --> BO3[CaregiverAssignment<br/>resolved = backed_out]
        BO3 --> BO4[Admin notified via email]
        BO4 --> BO5[Booking status & caregiver_id<br/>unchanged — Admin handles manually]
    end

    CO3 --> P1
    P5 --> R1
    P5 --> R3
```

> **Known gaps:** See `docs/caregiver-backout-gaps.md` for issues with the booking detail page, auto-resolve on reassign, replace caregiver flow, and other gaps in the backout/cancellation flow.

## Financial Model

```mermaid
flowchart TD
    PR[PricingRule<br/>service_type + number_of_children] -->|lookup| HR[Hourly Rates]
    HR --> CTH[charge_to_client_hourly]
    HR --> PTG[paid_to_caregiver_hourly]
    HR --> SWC[sitterwise_cut_hourly]

    CTH -->|× total_working_hour| CC[charge_to_client]
    PTG -->|× total_working_hour| PG[paid_to_caregiver]
    SWC -->|× total_working_hour| SC[sitterwise_cut]

    CC --> TSA[total_service_amount<br/>= charge_to_client<br/>+ reimbursement + bonus]
    TSA --> TA[total_amount<br/>= total_service_amount + tip]

    TA -->|Stripe charge| SP[Service Payment]
    tip[Tip] -->|Separate Stripe charge| TP[Tip Payment]
```

## Data Model

```mermaid
erDiagram
    BookingGroup ||--o{ Booking : has
    BookingGroup {
        int client_id FK
        string service_type
        string location_type
        string submission_type
        json children
        json pets
        string address_line1
        int hotel_id FK
        boolean requires_payment
    }

    Booking {
        int booking_group_id FK
        int caregiver_id FK
        datetime start_datetime
        datetime end_datetime
        string status
        string payment_status
        decimal charge_to_client
        decimal paid_to_caregiver
        decimal sitterwise_cut
        decimal total_amount
        int reserved_by FK
        datetime reservation_expires_at
    }

    Booking ||--o{ BookingCaregiverNotification : invitations
    Booking ||--o| CaregiverAssignment : assignment
    Booking ||--o{ BookingAvailabilitySlot : reserves
    Booking ||--o{ ClientPayment : payments
    Booking ||--o{ BookingRating : ratings

    BookingAvailabilitySlot {
        int booking_id FK
        int caregiver_id FK
        int availability_id FK
        date date
        string time_slot
    }

    Availability ||--o{ BookingAvailabilitySlot : consumed_by
```

## Group Booking

A single multi-date request creates one **BookingGroup** (header with shared fields) containing multiple **Bookings** (one per date/time slot). The `HasGroupFields` trait on `Booking` transparently delegates reads of shared fields (`service_type`, `children`, `pets`, `address`, etc.) to the parent group.

### Creation

```mermaid
flowchart TD
    A[Guest/Client/Admin submits form<br/>with dates array] --> B[Create BookingGroup<br/>shared fields: client, location,<br/>service_type, children, pets]
    B --> C[Create Booking per date<br/>per-date fields: start/end,<br/>caregiver, financials, status]
    C --> D{dates count > 1?}
    D -->|No| E[Fire BookingCreated<br/>single-date email]
    D -->|Yes| F[Fire BookingGroupCreated<br/>group email with all dates]
```

### Caregiver Assignment (All-or-Nothing)

```mermaid
flowchart TD
    A[Admin notifies caregivers] --> B{Group has >1 booking?}
    B -->|No| C[Caregiver reserves single booking<br/>60s TTL]
    B -->|Yes| D[Caregiver reserves ALL bookings<br/>in group atomically<br/>60s TTL]
    C --> E{Confirm within TTL?}
    D --> E
    E -->|Yes| F[All bookings confirmed<br/>CaregiverAssignment per booking]
    E -->|No| G[All bookings revert to received]
```

### Splitting

Admin can split a group — move some bookings to a new `BookingGroup`. After splitting, each sub-group operates independently (separate caregiver assignments, separate lifecycle).

Extracted bookings have their `caregiver_id` reset to null. `AdminBookingService::splitGroup()` explicitly calls `AvailabilityReservationService::release()` for each extracted booking (the raw DB update bypasses Eloquent events).

```mermaid
flowchart TD
    S1[Admin selects bookings to split] --> S2[Create new BookingGroup<br/>submission_type = admin]
    S2 --> S3[Move bookings: caregiver_id = null<br/>status = received]
    S3 --> S4[Release availability slots<br/>for each extracted booking]
    S4 --> S5[Fire BookingGroupSplit event]
```

```mermaid
flowchart LR
    subgraph Before["Before Split"]
        BG1[BookingGroup<br/>3 bookings] --> B1[Booking 1]
        BG1 --> B2[Booking 2]
        BG1 --> B3[Booking 3]
    end

    subgraph After["After Split"]
        BG2[BookingGroup A<br/>2 bookings] --> B4[Booking 1]
        BG2 --> B5[Booking 2]
        BG3[BookingGroup B<br/>1 booking] --> B6[Booking 3]
    end

    Before -->|Admin splits| After
```

### Payment

Payment is **per-booking**, not per-group. Each booking is charged independently via `JobBillingService::charge()`. A group is fully paid when all its child bookings reach `status = paid`.

### Splitting & Availability

When a group is split, extracted bookings lose their `caregiver_id`. `AdminBookingService::splitGroup()` calls `AvailabilityReservationService::release()` for each extracted booking after the raw DB update (which bypasses Eloquent events).

## Half-Day Slot Mapping

The system divides each day into three half-day blocks (`TimeSlotHelper`). Bookings map to one or more blocks based on their time range:

| Slot | Time Range | Example booking | Required slots |
|---|---|---|---|
| Morning | 06:00 – 12:00 | 8:00 AM – 10:00 AM | `[morning]` |
| Afternoon | 12:00 – 18:00 | 1:00 PM – 3:00 PM | `[afternoon]` |
| Evening | 18:00 – 23:00 | 7:00 PM – 9:00 PM | `[evening]` |
| Cross-slot | — | 11:00 AM – 5:00 PM | `[morning, afternoon]` |
| Full day | — | 8:00 AM – 10:00 PM | `[morning, afternoon, evening]` |

### How overlapping works

A booking overlaps a slot if its time range has any intersection with the slot's window:

```
overlap if: bookingStart < slotEnd AND bookingEnd > slotStart
```

This means a booking ending at **18:00:00** does NOT overlap evening (18:00 > 18:00 = false), but a booking ending at **18:00:01** does overlap evening.

### Used slot subtraction

When checking availability, the system:
1. Determines required slots for the new booking
2. Loads the caregiver's `Availability.time_slots` for that date
3. Loads `BookingAvailabilitySlot` records (used slots) for that date
4. Computes `freeSlots = time_slots - usedSlots`
5. Checks `coveredSlots = requiredSlots ∩ freeSlots`
6. If `coveredSlots < requiredSlots`, caregiver is not available

## Calendar Visual States

The availability calendar (admin & caregiver dashboard) shows three visual states:

| State | Appearance | Meaning |
|---|---|---|
| **Available** | Colored icons (yellow Sunrise, teal Sun, blue Moon) | Caregiver set this slot, no booking conflict |
| **Booked** | Same icons, muted/gray (`opacity-30`) | Slot is set but occupied by a confirmed/received booking |
| **Not set** | Blank (—) | Caregiver never set availability for this date |

### Backend data flow

Each availability record includes a `booked_slots` array computed from the `usedSlots` relationship:

```
Availability
  ├─ time_slots: ['morning', 'afternoon', 'evening']   (what caregiver set)
  └─ booked_slots: ['morning']                           (what a booking consumes)
      → frontend renders: morning = gray, afternoon = colored, evening = colored
```

### Date clickability

- **Fully booked** (all `time_slots` are in `booked_slots`): date is NOT clickable — no "Add"/"Edit" overlay, `cursor-default`.
- **Partially booked**: date remains clickable — only free slots are editable.
- **No availability set**: date is clickable — "Add" overlay appears.

## Notifications

Each booking lifecycle event triggers notifications to specific recipients via configured channels.

```mermaid
sequenceDiagram
    participant Guest as Guest/Client/Admin
    participant System
    participant Caregiver as Caregiver
    participant Client as Client
    participant Admin as Admin

    Note over Guest,Admin: CREATION
    Guest->>System: Submit booking

    alt Single date
        System->>System: Fire BookingCreated
        System->>Client: Email + DB notification (1×)
        System->>Admin: Email + DB notification (1× per admin)
    else Multi-date (N dates)
        System->>System: Fire BookingGroupCreated
        System->>Client: Email with all dates listed (1× only)
        System->>Admin: Email with all dates listed (1× per admin)
    end

    Note over Guest,Admin: CAREGIVER INVITATION
    Admin->>System: Notify caregiver(s)
    System->>System: Create N BookingCaregiverNotification records
    System->>Caregiver: Email + SMS + DB notification (1×)

    Note over Caregiver: Caregiver sees N notification records<br/>grouped in UI by booking_group_id

    Note over Guest,Admin: RESERVATION & CONFIRM
    Caregiver->>System: Reserve (atomic: all N bookings)
    System-->>Caregiver: Countdown 60s

    Caregiver->>System: Confirm (atomic: all N bookings)
    System->>System: Fire BookingAccepted (1× only)
    System->>Client: Email + SMS + DB notification (1×)
    System->>Caregiver: Email + DB notification (1×)
    System->>Admin: Email + DB notification (1× per admin)
```

### Notification Events

For a group with **N dates**, `BookingInvitationSent` creates **N separate `BookingCaregiverNotification` records** (one per booking row). All other events fire **once** regardless of date count.

| Event | Trigger | Fires | Recipients | Channels | Notes |
|---|---|---|---|---|---|
| `BookingCreated` | Single booking created | 1× per booking | Client, all admins | `database`, `mail` | Uses `BookingCreatedNotification` (SendGrid template) |
| `BookingGroupCreated` | Multi-date group created | 1× per group | Client, all admins | `mail` only | Uses `ClientGroupBookingCreatedMail` / `AdminGroupBookingCreatedMail` — lists all dates |
| `BookingInvitationSent` | Admin notifies caregiver(s) | 1× per caregiver | That caregiver | `database`, `mail`, SMS | Creates N `BookingCaregiverNotification` rows (one per booking date) |
| `BookingAccepted` | Caregiver confirms | 1× per confirm action | Client, caregiver, all admins | `database`, `mail` (+ SMS for client) | Fires once from `CaregiverBookingService::confirm()` — all recipients notified simultaneously |

### Notification Channels by Recipient

| Channel | Client | Caregiver | Admin |
|---|---|---|---|
| `database` (in-app) | ✓ | ✓ | ✓ |
| `mail` (SendGrid) | ✓ | ✓ | ✓ |
| `Sms` (Twilio) | ✓ | ✗ | ✗ |

### Environment Guards

In non-production environments, notifications are guarded to prevent accidental delivery to real recipients:

- **Mail:** If `config('mail.default')` is a deliverable driver (`sendgrid`, `ses`, `postmark`, `mailgun`, `resend`), it is overridden to `log`.
- **SMS:** The `TwilioService` is replaced with a dry-run implementation that logs to the application log instead of sending via Twilio API.

See `AppServiceProvider::guardMailInNonProduction()` and `AppServiceProvider::guardSmsInNonProduction()`. Both methods are no-ops in production.
