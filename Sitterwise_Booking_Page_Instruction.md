**Sitterwise Booking Page**

*Copy & Layout Reference for Aji*

Prepared May 2026

This document covers every text change and layout adjustment for the new client booking page. All existing form fields, validation rules, and data structures stay the same — only the visible copy, grouping, and a few small UI behaviors are changing.

Where the "Current" column says (none), that's a new piece of copy being added. Where it says (unchanged), the label stays as-is and is listed only so you have a complete checklist.

**1\. Page Background & Hero**

*Centered hero at the top of the page, above the form cards. Should match the styling and warmth of the existing login page ("It's you\! We're so happy you're here.").*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Page background color** | *\#F5F7F8 (light gray)* | \#FDF5F2 (Sitterwise blush) |
| **Logo** | *Text "sitterwise"* | Use real logo: sitterwise.com/wp-content/uploads/2026/02/sitterwise.png |
| **Heading** | *Book a Caregiver* | It's you\! We're so happy you're here. |
| **Heart icon** | *(none)* | Coral heart centered below the heading on its own line — match the login page heart styling |
| **Subheading** | *Find the perfect caregiver for your needs* | Tell us about your family, your plans, and what you need — we'll handle the rest. |
| **Legacy line** | *(none)* | Matching San Diego families with trusted caregivers since 1981\. |

*Heading font: Playfair Display, navy \#1B3A5C. Subheading and legacy line: Poppins, gray \#5A6B73. Legacy line is italic and slightly smaller than the subheading.*

**2\. "About You" Card**

*First card. Keep the existing teal section header bar (\#E8F5F5). Add a small italic helper line directly under the section heading.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Section header** | *Your Details* | About You |
| **Header helper line** | *(none)* | So we know who to send the confirmation to. |
| **First Name** | *First Name \** | (unchanged) |
| **Last Name** | *Last Name \** | (unchanged) |
| **Email** | *Email \** | (unchanged) |
| **Phone** | *Phone \** | (unchanged) |
| **Referral dropdown label** | *How Did You Hear (was in second card)* | How did you find us? — MOVED to About You, placed after Phone |

*"How did you find us?" was previously in the second card at the bottom. Moved up here because asking it during introductions feels more natural than after someone has filled out the whole form.*

**3\. "About Your Booking" Card**

*Second card. Inside the card body, the form is broken into five visual subsections, each with a small uppercase header and a teal vertical accent line (3px solid \#84D0D2) running down the left side. This breaks up the long form so it's not overwhelming.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Section header** | *Booking Details* | About Your Booking |
| **Header helper line** | *(none)* | The more you share, the better we can match. |

**3.1  Subsection: WHEN & WHERE**

*First subsection inside the second card. Contains service type, location type, stackable date entries, address, and a complex-booking note.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Subsection header** | *(none)* | WHEN & WHERE (small uppercase, navy, slight letter-spacing) |
| **Service Type** | *Babysitter (dropdown)* | (unchanged) — applies to all dates |
| **Location Type** | *Private Home (dropdown)* | (unchanged) — applies to all dates |
| **Date block label** | *Start/End Date and Time (single instance)* | Each date entry now lives in its own sub-card labeled "Date 1", "Date 2", etc. — see notes below |
| **Add another date button** | *(none)* | \+ Add another date (full-width, dashed teal border) |
| **Date remove link** | *(none)* | × Remove (small coral text in the corner of each date block beyond Date 1\) |
| **Address placeholder** | *Start typing address...* | Where will the caregiver meet you? |
| **Complex booking note** | *(none)* | Need different locations or a more complex schedule? Add the details in Notes to Sitterwise at the bottom of the form, and our Care Team will take it from there. |

*Date stacking behavior: Date 1 is always present and cannot be removed. Each additional date adds a numbered block ("Date 2", "Date 3"...) with a Remove link. All dates share the same Service Type, Location Type, and Address — for anything more complex, families use the Notes to Sitterwise field (the complex booking note tells them this).*

*Complex booking note styling: soft blush background \#FDF5F2 with a coral-tinted border (\#F0C5BA or similar), small italic text, bold lead-in phrase. Sits at the bottom of the When & Where subsection.*

**3.2  Subsection: WHO'S BEING CARED FOR**

*Children list with a small coral heart next to each added child, plus a single Special Needs / Allergies textarea below.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Subsection header** | *(none)* | WHO'S BEING CARED FOR |
| **Children empty state** | *No children added* | Add each child so we can match the right caregiver |
| **Children validation error** | *At least one child is required.* | Please add at least one child |
| **Child row styling** | *(none)* | Each added child shows: coral heart ♥ | Name | Age (e.g., "4 years old") | × remove button. White background, soft teal border, small padding. |
| **Add New Child helper** | *(none)* | Birth month and year — we'll keep their age up to date as they grow. |
| **Age field behavior** | *Shows "Age unknown" until month \+ year are filled* | Hide the Age column entirely until both Month and Year are filled, then show calculated age (e.g., "4 years old") |
| **Special Needs / Allergies label** | *Special Needs / Allergies* | (unchanged) |
| **Special Needs / Allergies placeholder** | *Special needs notes* | Allergies, medications, anything we should know... |

*The little heart on each child row is a small but meaningful touch — it picks up the same coral as the hero heart, tying the page together visually and making the form feel less like data entry.*

**3.3  Subsection: YOUR HOUSEHOLD**

*Pets list plus the "anyone else home" checkbox, which has a conditional nudge.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Subsection header** | *(none)* | YOUR HOUSEHOLD |
| **Pets empty state** | *No pets added* | Add any pets your caregiver should know about |
| **Checkbox label** | *Other Adults Present* | Will anyone else be home? |
| **Conditional nudge** | *(none)* | Please add a quick note below in Notes for Caregiver letting your caregiver know who else will be home (a spouse working from home, an older sibling, a grandparent, etc.). |

*Conditional nudge behavior: only appears when the checkbox is checked. Styling: soft teal background \#E8F5F5 with 3px teal left border, small italic text, indented to align with the checkbox label (not the checkbox itself). Bold lead-in ("Please add a quick note below") with the field name ("Notes for Caregiver") in italics.*

**3.4  Subsection: SITTER PREFERENCES**

*The four preference checkboxes.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Subsection header** | *Sitter Preferences* | SITTER PREFERENCES (small uppercase) |
| **Section intro line** | *(none)* | Any of these apply? Check what fits — we'll factor it into the match. |
| **Checkbox 1** | *Baby Specialist* | (unchanged) |
| **Checkbox 2** | *Special Needs Care* | (unchanged) |
| **Checkbox 3** | *Willing to Swim* | (unchanged) |
| **Checkbox 4** | *Child is Sick* | Sick Day Care |

**3.5  Subsection: NOTES**

*The two notes textareas — for the caregiver and for the Sitterwise Care Team.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Subsection header** | *(none)* | NOTES |
| **Notes for Caregiver label** | *Notes for Caregiver* | (unchanged) |
| **Notes for Caregiver placeholder** | *Any additional notes for the caregiver...* | Plans for the day, dress code, what to bring, anything to expect — pool time, a movie night, attending an event together... |
| **Notes to Sitterwise label** | *Notes to Sitterwise* | (unchanged) |
| **Notes to Sitterwise placeholder** | *Any notes for Sitterwise...* | Anything you'd like our Care Team to know — including extra dates or different locations |

**4\. Submit Block**

*Right-aligned at the bottom of the page, below the second card. Reassurance text sits above the button. The next page is the payment page.*

| Field | Current | Replace with |
| :---- | :---- | :---- |
| **Reassurance line** | *(none)* | Next, you'll add a payment method to confirm your reservation. We'll begin matching as soon as it's on file. |
| **Submit button** | *Submit Booking Request* | Continue to Payment |

*Reassurance line: italic gray \#5A6B73, smaller than body text, right-aligned. Button: coral \#F48A91 (existing styling).*

**5\. Styling & Layout Notes**

**Colors (Sitterwise brand — use only these)**

* Page background: blush \#FDF5F2

* Form cards: white \#FFFFFF

* Section header bars (inside cards): teal \#E8F5F5

* Primary text and labels: navy \#1B3A5C

* Helper text and italics: gray \#5A6B73

* Subsection accent line and heart: coral \#F48A91 (heart) and teal \#84D0D2 (accent lines)

* Field borders: light teal \#C6E7E7

* Required field asterisk: coral \#F48A91

**Fonts**

* Hero heading: Playfair Display, 500 weight

* Everything else: Poppins

* Subsection headers (WHEN & WHERE, etc.): Poppins, uppercase, 600 weight, slight letter-spacing (\~0.6px)

**Subsection structure**

* Each of the five subsections inside "About Your Booking" has a teal left border (3px solid \#84D0D2) with \~16px left padding, creating a clear visual group.

* Subsections are separated by \~22px vertical margin so the eye gets clear breaks.

* Subsection headers are small (13px), uppercase, navy, with slight letter-spacing — distinct enough to act as group markers but small enough not to compete with the main section headers.

**Empty state styling**

* Empty state boxes (Children, Pets when nothing added): dashed teal border \#C6E7E7, blush background \#FDF5F2, gray italic text, \~22px padding, centered text.

**Date block styling**

* Each date entry is a sub-card inside the When & Where subsection: subtle off-white background (\#FDFCFA), light teal border, \~14px padding.

* Date 1 has just the label "Date 1". Date 2 and beyond add a small × Remove link to the right of the label.

* "+ Add another date" button is full-width, white background, dashed teal border — looks like an action zone, not a primary CTA.

**Child row styling**

* Each child row after being added: white background, light teal border, \~12-14px padding, \~8px margin between rows.

* Layout: coral heart ♥ on the left (16px), then child info (name in navy 500-weight, age in gray smaller), then × remove button on the right (subtle gray, turns coral on hover).

**Conditional UI elements**

* "Will anyone else be home?" nudge: appears only when checkbox is checked, soft teal background with teal left border.

* Child age field: column is hidden until both Month and Year are selected, then calculated age appears.

* Both should ideally use a smooth fade or slide-down transition, but plain show/hide works if simpler.

**6\. Quick Checklist**

Working through the changes in order:

* Change page background to \#FDF5F2

* Swap text "sitterwise" for the actual logo image

* Rewrite hero with new heading, coral heart, subheading, and legacy line

* Rename "Your Details" → "About You" and add helper line

* Move "How did you find us?" up into About You after Phone

* Rename "Booking Details" → "About Your Booking" and add helper line

* Restructure About Your Booking into 5 subsections with teal accent lines

* Add WHEN & WHERE subsection with stackable date blocks and "+ Add another date" button

* Add complex-booking note inside When & Where

* Update Address placeholder

* Add WHO'S BEING CARED FOR subsection with new child row styling and birth-date helper

* Add coral hearts next to each added child

* Hide age column until month \+ year are filled

* Update Children empty state and validation error copy

* Update Special Needs / Allergies placeholder

* Add YOUR HOUSEHOLD subsection

* Update Pets empty state

* Rename "Other Adults Present" → "Will anyone else be home?"

* Add conditional nudge below the checkbox

* Rename Sitter Preferences subsection header to uppercase and add intro line

* Rename "Child is Sick" → "Sick Day Care"

* Add NOTES subsection

* Update Notes for Caregiver and Notes to Sitterwise placeholders

* Add reassurance line above submit

* Rename submit button to "Continue to Payment"

*Questions on any of this — just ping Amy. The flow has been thought through end-to-end but real-world testing will surface tweaks.*