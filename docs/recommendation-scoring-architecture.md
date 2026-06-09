# Recommendation Scoring Architecture

## Goal
Replace the rigid 6-tier recommendation system with a flexible weighted scoring model that ranks caregivers by how well they match a specific booking. Criteria should be easy to add, remove, or rebalance without restructuring the algorithm.

---

## Problem With Tiers

The original tier system assigned each caregiver to exactly one mutually exclusive tier:

| Tier | Label | Criteria |
|------|-------|----------|
| 1 | Previous Caregiver | Previously worked with client |
| 2 | Excellent Match | Available + Specialty + Preferred location |
| 3 | Good Match | Specialty + Willing location |
| 4 | Fair Match | Recent work (3mo) + Specialty + Preferred location |
| 5 | Potential Match | Recent work (6mo) + Any fit |
| 6 | Available | Everyone else |

Key issues:
- **Tier 1 short-circuits everything.** A previous caregiver who is unavailable and has no matching specialty still ranks above an available, specialty-matched, local caregiver.
- **Mutually exclusive.** A caregiver who matches 4 criteria is treated the same as one who matches 3, as long as they land in the same tier.
- **Hard to extend.** Adding or reordering criteria requires rewriting the entire tier assignment method.

---

## Solution: Weighted Scoring

Each criterion has a numeric weight. A caregiver's **score** = sum of weights for all matched criteria. Sorting is by score descending (then by name). The score is internal only — the frontend receives `matchIcons` for transparency.

### Priority Order

1. Available + Favorited by client
2. Available + Specialty match + Preferred location
3. Available + Specialty match + Willing location
4. Recent work (3mo) + Specialty + Preferred location
5. Recent work (6mo) + Any fit (specialty, preferred location, or willing location)
6. Everyone else

### Weights

Weights are designed so that no combination of lower-priority criteria can outrank a higher-priority combination:

| Criteria | Weight |
|----------|--------|
| Available + Favorited (bonus, requires both) | 100000 |
| Available | 10000 |
| Specialty match | 1000 |
| Preferred location | 100 |
| Willing location | 10 |
| Recent work (3mo) | 3 |
| Previous work with client | 2 |
| Recent work (6mo) | 1 |

These values ensure the priority order is preserved — e.g., `available (10000) + specialty (1000) + preferred (100) = 11100` which is always > `available (10000) + specialty (1000) + willing (10) = 11010`.

### Score Computation

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

Derived directly from matched attributes (not from score range):

| Icon | Attribute |
|------|-----------|
| `heart` | Favorited by client |
| `check-circle` | Available |
| `star` | Specialty match |
| `map-pin` | Preferred location |
| `map-pin-off` | Willing (non-preferred) location |
| `clock` | Recent work |
| `history` | Previous work with client |

---

## Specialty Matching

Two separate mechanisms feed into the `specialty` boolean (OR logic):

### 1. `matchesServiceType()` — age-group SpecialtyTypes + baby_specialist

Checks caregiver's age-group specialties (`SpecialtyType`) against:

**Service type mapping:**
| Service Type | Required Specialty Types |
|---|---|
| `babysitter` | Babies, Toddlers, Preschool, School Age |
| `group_childcare_invoiced` | Babies, Toddlers, Preschool, School Age |

**Sitter preference expansion:**
| Sitter Preference | Adds Specialty Type |
|---|---|
| `baby_specialist` | Babies |

**Companion care** (not an age group) checks the EAV `special_needs` attribute directly within this method.

### 2. `matchesSitterPreferences()` — EAV attributes for non-age-group preferences

| Sitter Preference | Caregiver Attribute (EAV) |
|---|---|
| `special_needs_care` | `special_needs` (boolean) |

This is extensible — new mappings can be added here.

---

## Data Flow

```
Client selects sitter_preferences in profile
  └─ stored in clients.sitter_preferences (JSON array)
       │
Booking created (copies/preferences from client, or overrides)
  └─ stored in booking_groups.sitter_preferences
       │
CaregiverRecommendationService.getRecommendedCaregivers()
  ├─ Resolves sitter_preferences from booking (fallback to client)
  ├─ Resolves favorite_caregivers from client relationship
  ├─ Fetches all active caregivers with eager-loaded relations
  ├─ Computes attributes for each caregiver
  │    ├─ matchesServiceType (age-group SpecialtyTypes + baby_specialist)
  │    └─ matchesSitterPreferences (EAV attributes for special_needs_care)
  ├─ Computes score = weighted sum of matched attributes
  ├─ Derives matchIcons from matched attributes
  └─ Returns sorted by score desc, then name asc
```

---

## Future Extensibility

### Adding a new criterion

1. Add the check to `computeAttributes()` (e.g., `$hasCPR = $caregiver->certifications->contains(...)`)
2. Add a weight to `SCORE_WEIGHTS`
3. Optionally add an icon to the icon map

### Adding a new sitter preference → caregiver attribute mapping

1. Seed a new `AttributeDefinition` (if using EAV) or add a column
2. Add the mapping to `matchesSitterPreferences()`

### Rebalancing

Simply adjust the weight constants. No structural changes needed.

---

## Files

- `app/Services/CaregiverRecommendation/CaregiverRecommendationService.php` — scoring engine
- `app/Services/CaregiverRecommendation/LocationMatcher.php` — city→area matching
- `app/Enums/SitterPreference.php` — client-side preference values
- `app/Enums/ServiceType.php` — service type enum
- `app/Models/AttributeDefinition.php` — EAV attribute definitions (used for `special_needs`)
- `database/seeders/AttributeDefinitionSeeder.php` — seeds EAV attributes including `special_needs`
- `tests/Feature/Caregiver/RecommendationServiceTest.php` — test coverage
