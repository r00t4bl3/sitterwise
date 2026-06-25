# Caregiver Dashboard Gamification — Implementation Plan

## Overview

Augment the existing caregiver dashboard with gamification elements based on the designs in `caregiver-gamification.html` and `new-caregiver-dashboard.html`. No new database tables, routes, or controllers. All badge states are computed live on page load from existing data.

## Phases

### Phase 1: Trophy Case + TrustLine Card (4–5 hrs)

Adds the right-column cards (TrustLine, Trophy Case, Attention strip, mini stats) and the badge computation service.

### Phase 2: Lifesaver Job Cards (2–3 hrs)

Flags hard-to-fill bookings with a visible bonus on the job opportunity card.

### Phase 3: Badge Earned Moment (2–3 hrs)

Post-checkout badge detection and a celebratory dismissible card on the dashboard.

---

## Phase 1 — Detailed Breakdown

### Backend

#### New file: `app/Services/CaregiverBadgeService.php`

A single class with a `badgesFor(Caregiver $caregiver): array` method. Returns all badges with their state, tier, progress, and earned date.

**Badge definitions (all derived from existing data):**

| Group | Badge | Criteria | Tier |
|---|---|---|---|
| Getting Started | Ready, Set, Sit | Onboarding complete + training quiz done | teal |
| Jobs Completed | First Day | 1 completed job | teal |
| Jobs Completed | TrustLine Ten | 10 completed jobs | teal |
| Jobs Completed | Twenty-Five Club | 25 completed jobs | coral |
| Jobs Completed | Fifty Strong | 50 completed jobs | navy |
| Jobs Completed | Century Sitter | 100 completed jobs | navy |
| Jobs Completed | The Marion | 250 completed jobs | navy |
| Lifesavers | Lifesaver | 5 lifesaver jobs | coral |
| Lifesavers | First Responder | 10 lifesaver jobs | navy |
| Lifesavers | Guardian Angel | 25 lifesaver jobs | navy |
| Specialties | The Daymaker | 10 daytime jobs (8 AM–4 PM) | teal |
| Specialties | Hotel Pro | 10 hotel bookings | coral |
| Specialties | Event Ace | 5 event-childcare jobs | teal |
| Specialties | Infant Specialist | 10 jobs with children under 2 | teal |
| Five-Star Service | Family Favorite | 5 five-star reviews | teal |
| Five-Star Service | Beloved | 10 five-star reviews | coral |
| Five-Star Service | Legendary | 25 five-star reviews | navy |
| Sitterwise Family | One Year In | 1 year tenure | teal |
| Sitterwise Family | Three & Thriving | 3 years tenure | navy |
| Sitterwise Family | Heart of the House | 5 years tenure | navy |

**Queries executed (3–5 total, all indexed):**
- Caregiver's completed/paid bookings with `end_datetime` and `service_type` (for counts + specialty + earned dates)
- Caregiver's 5-star ratings count with `created_at` (for Five-Star group)
- Certifications pivot (for TrustLine status — already eager-loaded)
- Booking ratings join for review count

**Earned dates derivation:**
- Nth completed booking → nth row's `end_datetime`
- Nth five-star review → nth row's `created_at`
- Tenure badges → `caregiver.created_at + N years`
- For "Ready, Set, Sit" → none available yet (placeholder date or null)

#### Change: `app/Http/Controllers/DashboardController.php`

Add to the caregiver Inertia response:

```php
'badges' => app(CaregiverBadgeService::class)->badgesFor($caregiver),
'trustline' => [
    'certified' => $caregiver->certifications()
        ->where('certification_type_id', 3)
        ->whereNotNull('verified_at')
        ->exists(),
    'cleared_at' => $clearedAt, // formatted date string
],
'attention' => [
    'cpr_expiring' => $cprExpiringSoon, // boolean + date
    'needs_availability' => $noFutureAvailability, // boolean
],
```

Also pass `completedJobs` and `totalEarned` (already present) for the mini stats.

### Frontend — New Components

#### `resources/js/components/medallion.tsx`

Parameterized SVG medallion component. Props:

```typescript
interface MedallionProps {
    tier: 'teal' | 'coral' | 'navy';
    variant: string; // icon identifier
    earned: boolean;
    size?: 'sm' | 'md'; // sm for dashboard, md for milestones page
}
```

- Earned: full color fill (`#84D0D2` / `#F48A91` / `#1B3A5C`), white stroke
- Locked: gray fill (`#ECF0F2`), dashed outer ring, muted icon
- Icon variants map to specific SVG paths (checkmark, star, building, crosshair, etc.)

#### `resources/js/components/trustline-card.tsx`

Shows TrustLine clearance status. For verified caregivers: shield icon, "You're cleared" message, clearance date, confetti decoration. For non-verified: hidden (no card rendered).

Props:

```typescript
interface TrustlineCardProps {
    certified: boolean;
    clearedAt?: string;
}
```

#### `resources/js/components/attention-strip.tsx`

List of action items. Props:

```typescript
interface AttentionItem {
    icon: string; // lucide icon name
    title: string;
    description: string;
    actionLabel: string;
    actionHref: string;
}

interface AttentionStripProps {
    items: AttentionItem[];
}
```

Items computed server-side (CPR expiring, availability missing) and passed as structured data, never rendered conditionally from empty state.

#### `resources/js/trophy-case.tsx`

Renders a grid of medallions for the dashboard (compact, ~5–6 max). Props:

```typescript
interface TrophyBadge {
    name: string;
    tier: 'teal' | 'coral' | 'navy';
    variant: string;
    earned: boolean;
    earnedDate?: string;
    progress?: string; // e.g. "31 of 50"
}

interface TrophyCaseProps {
    badges: TrophyBadge[];
}
```

The dashboard version shows the 5 most recently earned + first few locked badges. Full collection is on the existing `/milestones` page (updated in a future phase).

### Frontend — Dashboard Changes

#### `resources/js/pages/dashboard/caregiver.tsx`

1. **Props interface** — Add `badges`, `trustline`, `attention`, `completedJobs`, `totalEarned` (last two may already come from stats)
2. **Right column order** (top to bottom): TrustLine card → Trophy Case → Attention strip → Upcoming Jobs → Availability Calendar → Mini stats
   - Only Upcoming Jobs and Availability Calendar exist today; the other three are inserted
3. **Mini stats** at right column bottom: two numbers (jobs completed, earned) in a quiet row

---

## Phase 2 — Lifesaver Job Cards (Concept)

- Add a `CaregiverLifesaverService` that marks a booking as a "Lifesaver" if unclaimed past a configurable threshold or starting within 24h
- Computed at runtime, no new DB column
- Job opportunity cards in the dashboard show a coral "Lifesaver" tag + bonus amount when flagged
- Bonus amount is configurable (env or config file), displayed as "+$X bonus" on the card

---

## Phase 3 — Badge Earned Moment (Concept)

- After checkout completes, run the badge service and compare against pre-checkout state
- If new badges earned, pass a `newlyEarnedBadges` array to the next dashboard page load
- Dashboard renders a celebratory card at the top (dismissible) showing the badge medallion + name + "New badge earned" message
- State is one-time (cleared after dismiss or after being shown)

---

## What's NOT changing

- No new database tables or migrations
- No new routes or controllers
- No cron jobs or queue workers
- No Leaderboards (design guardrail)
- No badge expiry or decay (design guardrail)
- No decline tracking (design guardrail)
