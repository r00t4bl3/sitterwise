# Availability — Week-List concept (handoff for Aji)

This is a **UX spec**, not code to paste. The attached HTML mock (`availability-week-list.html`) shows the interaction; below is everything needed to build it as a real Inertia/React component against the Laravel backend.

## The idea in one line

Keep the month calendar for context, but make each **week row** clickable. Clicking a week opens a modal listing that week's 7 days. The caregiver toggles Morning / Afternoon / Evening (or marks the day Booked) for as many days as they want, then **saves the whole week in one request**. This brings back the batch-entry feel of the old `availability.hotelchildcare.com` list without losing the calendar.

Mutually exclusive rule: **Booked** clears M/A/E for that day, and picking any of M/A/E clears Booked.

---

## 1\. Data contract

Each day a caregiver sets is four booleans. Suggested API shape (whatever the store, the wire format can stay this):

{

  "caregiver\_id": "01KVAG...",      // ULID, matches your existing convention

  "days": \[

    { "date": "2026-06-21", "morning": true,  "afternoon": true,  "evening": false, "booked": false },

    { "date": "2026-06-22", "morning": false, "afternoon": false, "evening": false, "booked": true  }

  \]

}

A day with all four false \= availability cleared for that date (delete the row / drop the key).

### Storage — your call, two options

- **Row per day** (`caregiver_availability`: `caregiver_id`, `date`, `morning`, `afternoon`, `evening`, `booked`, timestamps; unique on `caregiver_id + date`). Cleanest if the matching/booking query filters availability in SQL — e.g. "find caregivers free Saturday afternoon." Recommend this one given how booking assignment reads availability.  
- **JSON column per day** keyed by date. Fewer rows, but you can't index/query slots in SQL.

Pick based on how `CaregiverBookingService` / the recommendation query needs to read it. If matching ever filters by slot, go row-per-day.

### One thing to decide: does "Booked" come from caregivers or from the system?

In the mock, Booked is a manual toggle. But if a confirmed booking already sets a caregiver unavailable, **Booked may be derived state**, not something they set by hand — in which case render it read-only (greyed, "Booked") and drive it off the bookings table, not the availability write. Worth a 2-minute gut-check; it changes whether `booked` is writable.

---

## 2\. Endpoint (Inertia)

One batched write per week-save. Example:

// routes/web.php

Route::post('/availability/week', \[AvailabilityController::class, 'storeWeek'\])

    \-\>middleware('auth')

    \-\>name('availability.storeWeek');

// app/Http/Controllers/AvailabilityController.php

public function storeWeek(Request $request)

{

    $data \= $request-\>validate(\[

        'days'              \=\> \['required', 'array', 'max:7'\],

        'days.\*.date'       \=\> \['required', 'date'\],

        'days.\*.morning'    \=\> \['boolean'\],

        'days.\*.afternoon'  \=\> \['boolean'\],

        'days.\*.evening'    \=\> \['boolean'\],

        'days.\*.booked'     \=\> \['boolean'\],

    \]);

    $caregiver \= $request-\>user()-\>caregiver; // adjust to your relationship

    foreach ($data\['days'\] as $day) {

        $hasAny \= $day\['morning'\] || $day\['afternoon'\] || $day\['evening'\] || $day\['booked'\];

        if (\! $hasAny) {

            $caregiver-\>availability()-\>where('date', $day\['date'\])-\>delete();

            continue;

        }

        // Booked is exclusive — enforce server-side too, never trust the client

        if ($day\['booked'\]) {

            $day\['morning'\] \= $day\['afternoon'\] \= $day\['evening'\] \= false;

        }

        $caregiver-\>availability()-\>updateOrCreate(

            \['date' \=\> $day\['date'\]\],

            \[

                'morning'   \=\> $day\['morning'\],

                'afternoon' \=\> $day\['afternoon'\],

                'evening'   \=\> $day\['evening'\],

                'booked'    \=\> $day\['booked'\],

            \]

        );

    }

    return back(); // Inertia reloads availability prop; or return Inertia partial

}

Page load: pass the current month's availability down as an Inertia prop keyed by date, so the grid can render dots and the modal can pre-fill. `updateOrCreate` keeps the endpoint idempotent — saving the same week twice is harmless.

---

## 3\. React component sketch

This is the real artifact to build — the mock's vanilla JS maps almost 1:1 to local state.

// resources/js/components/availability-week-grid.tsx

import { useState } from 'react';

import { router } from '@inertiajs/react';

type Slots \= { morning: boolean; afternoon: boolean; evening: boolean; booked: boolean };

type DayState \= Record\<string, Slots\>; // key: 'YYYY-MM-DD'

const EMPTY: Slots \= { morning: false, afternoon: false, evening: false, booked: false };

export default function AvailabilityWeekGrid({ initial }: { initial: DayState }) {

  const \[saved, setSaved\] \= useState\<DayState\>(initial);   // persisted

  const \[openWeek, setOpenWeek\] \= useState\<string\[\] | null\>(null); // dates in the open week

  const \[draft, setDraft\] \= useState\<DayState\>({});         // unsaved edits in the modal

  function openModal(weekDates: string\[\]) {

    const d: DayState \= {};

    weekDates.forEach((date) \=\> (d\[date\] \= { ...(saved\[date\] ?? EMPTY) }));

    setDraft(d);

    setOpenWeek(weekDates);

  }

  function toggle(date: string, slot: keyof Slots) {

    setDraft((prev) \=\> {

      const cur \= { ...prev\[date\] };

      if (slot \=== 'booked') {

        const next \= \!cur.booked;

        return { ...prev, \[date\]: { ...EMPTY, booked: next } };

      }

      return { ...prev, \[date\]: { ...cur, booked: false, \[slot\]: \!cur\[slot\] } };

    });

  }

  function saveWeek() {

    const days \= Object.entries(draft).map((\[date, s\]) \=\> ({ date, ...s }));

    router.post('/availability/week', { days }, {

      preserveScroll: true,

      onSuccess: () \=\> {

        setSaved((prev) \=\> ({ ...prev, ...draft }));

        setOpenWeek(null);

      },

    });

  }

  // ...render month grid (weeks as clickable rows) \+ modal from \`openWeek\`/\`draft\`

}

Everything else (dots in the grid, the highlight-on-hover, the day-count footer) is presentational and lifted straight from the mock's CSS.

---

## 4\. ⚠️ The one gotcha: chip styling will get clobbered

When this renders inside the dashboard, the app's **global button styles (Tailwind/base button rules) leak into the modal chips** and blow them up into full-width solid blocks — Amy hit exactly this. The chips must stay small outline pills, coral only when selected.

Fix: scope the chip styles tightly so they win — either a dedicated component class with the key properties locked (`width:auto`, `display:inline-flex`, the outline default, the coral `.on` state), or Tailwind utilities applied directly on the element with no inherited `<button>` base bleeding through. In the standalone mock this was forced with `!important`; in the real component, prefer scoping/explicit utilities over `!important`, but the *symptom to watch for* is "pills turned into slabs."

---

## 5\. Brand tokens (already Sitterwise)

Coral `#F48A91` (selected) · Navy `#1B3A5C` (modal header, evening dot) · Logo teal `#84D0D2` (borders, afternoon-ish) · Teal bg `#E8F5F5` (week hover) · Blush `#FDF5F2` (today) · **Morning dot yellow `#F2C14E`** · Booked grey `#9aa6ad` · Playfair Display headings · Poppins body · square primary/secondary buttons (radius 0).

