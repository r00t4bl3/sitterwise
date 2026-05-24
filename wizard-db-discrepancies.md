# Caregiver Application Wizard — Database Discrepancies

## A. Relational records now created from wizard data ✅

| # | Admin section | Wizard data | Expected DB target | Status |
|---|---|---|---|---|
| 1 | Specialties | `age_groups.babies/toddlers/preschool/school_age` | `caregiver_specialties` pivot → `specialty_types` | ✅ Created in `submit()` |
| 2 | Locations | `location.north_county`, `location.south_east_county` | `caregiver_locations` pivot → `locations` | ✅ Created in `submit()` |
| 3 | Education | `education.*` (level, college, grad_year, degree, etc.) | `caregiver_educations` table | ✅ Created in `submit()` |
| 4 | Attributes | `position.petsitting`, `qualifications.driving`, `smokes` | `entity_attribute_values` pivot → `attribute_definitions` | ✅ Created in `submit()` |

## B. Value mismatches — handled

| # | Field | Wizard value(s) | How handled |
|---|---|---|---|
| 5 | `education.level` | `associate`, `bachelor`, `master`, `phd` | `education_type` changed from enum to string — stores wizard values directly |
| 6 | `location.south_east_county` | `"South / East County"` key | Mapped to Location id:1 (`"South County"`) — name mismatch in code |
| 7 | `location.flexible` | boolean flag | Stored in `caregivers.metadata.location_flexible` |
| 8 | `position.babysitting`, `petsitting`, `group_events` | boolean flags | Not mappable to specialties (service types vs age groups) — skipped |

## C. Wizard qualification booleans with no AttributeDefinition (skipped)

| # | Wizard group | Fields with no DB match |
|---|---|---|
| 9 | `qualifications.*` | `special_needs`, `companion_care`, `sick_care`, `work_from_home`, `dogsitting`, `catsitting`, `swimming`, `overnight_care` (driving maps to `has_vehicle`) |

## D. Wizard data now saved to Caregiver model columns ✅

| # | Caregiver column | Wizard field | Status |
|---|---|---|---|
| 10 | `biography` (string) | `bio` | ✅ Set in `submit()` |
| 11 | `languages` (string) | `languages` | ✅ Set in `submit()` |
| 12 | `education_level` (string) | `education.level` | ✅ Set in `submit()` |
| 13 | `metadata` (JSON cast) | `smokes`, `alcohol`, `substance_abuse`, `limitations`, `allergic_to_pets`, `visible_tattoos`, `authorized_to_work`, `reliable_vehicle`, `cpr_certified`, `trustline_certified`, `has_children`, `employment_status`, `current_employer`, `things_i_bring`, `interests`, `availability.*`, `location.flexible` | ✅ Set in `submit()` |

## E. Legacy model relationships (bypassed by design)

| # | Relationship | Target table | Used by submit()? |
|---|---|---|---|
| 14 | `references()` | `caregiver_references` | No — uses `ReferenceRequest` instead |
| 15 | `sponsors()` | `caregiver_sponsors` | No — uses `ReferenceRequest` (`is_sponsor`) instead |

## F. Admin page display — now populated ✅

| # | Admin section | Expected data | Status |
|---|---|---|---|
| 16 | Specialty tags | Age group badges with colors | ✅ Populated from `specialtyTypes` relationship |
| 17 | Location list | Locations with is_preferred | ✅ Populated from `locations` relationship |
| 18 | Education cards | School + type + year + degree | ✅ Populated from `educations` relationship |
| 19 | Attribute badges | Checkmark list of truthy attributes | ✅ Populated from `attributes` relationship |
