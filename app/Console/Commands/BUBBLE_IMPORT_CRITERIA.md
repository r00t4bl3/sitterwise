# Clients
- Dropped 77 columns — empty ones and caregiver fields that don’t belong in a client record (Bio, CPR, SS#, Sponsor Name, Background Check, etc.)
- Fixed name capitalization (all-caps and all-lowercase corrected to Title Case)
- Standardized phone numbers to (XXX) XXX-XXXX format — international numbers kept as-is
- Parsed the free-text “Names and ages of kids” field into structured columns: child_1_name, child_1_age, child_1_gender, child_1_est_birth_year (up to 6 kids)
- Added last_booking_date by cross-referencing the jobs file
- Applied hotel client filter — 711 one-time hotel clients archived separately, 4,778 migrated
# Jobs
- Dropped internal/system columns (API IDs, Slug, profile links, color codes, etc.)
- Fixed name capitalization, standardized phones
- Normalized pets field (yes/no where possible, descriptive text preserved)
- Standardized all date fields to YYYY-MM-DD HH:MM
- Split into 3 tabs: All, Active Only, Upcoming
# Ratings
- Split into two separate sheets — caregiver ratings (with stars + written feedback) and client ratings (caregiver’s assessment of the family)
- Nulled out 0-star ratings (confirmed as default placeholder values, not genuine scores)
# Transactions
- Dropped 9 ghost rows (no amount, no payment intent ID)
- Converted all amounts from cents to dollars (Stripe stores in cents) (Keep in cents as this is the value we got from Stripe)
- Standardized dates, dropped empty/system columns
- Flagged 105 rows with missing status for review
