# Implementation Plan: SQLite Staging, Resumption, and Live Insertion

## 1. Overview
The goal is to transition the `import:bubble` command from a simple scraper to a resilient synchronization engine. By using SQLite as a staging area, we can handle interrupted downloads, avoid redundant network requests, and provide a safe path for data transformation.

## 2. SQLite Staging Database
A dedicated SQLite database will be used to store raw records exactly as they are received from Bubble.io.

*   **File Path:** `storage/app/bubble_staging.sqlite`
*   **Table Schema (`staged_records`):**
    *   `type`: string (e.g., 'user', 'jobs')
    *   `external_id`: string (Bubble's `_id`)
    *   `modified_at`: integer (Bubble's `Modified Date` in milliseconds)
    *   `raw_json`: text (The full record source)
    *   `last_synced_at`: timestamp (When it was last fetched from web)
    *   `last_imported_at`: timestamp (When it was last processed into the App DB)
*   **Unique Index:** Composite index on `(type, external_id)`.

## 3. The "Smart Sync" Browser Loop
The scraping process will be updated to be more intelligent and interactive.

### A. Sorting
Before starting the capture, the browser will:
1.  Click the "Modified Date" column header in the Bubble UI.
2.  Verify the sort order is Descending (newest first).
*Benefit: This ensures that any new or updated records on Bubble are always at the top of our scrape.*

### B. Fast-Forward (Resumption)
When starting a scrape for a type that already has local data:
1.  Count existing local records for that type (e.g., 2,400).
2.  Calculate required skip count (e.g., 2,400 / 50 = 48 clicks).
3.  Execute a JavaScript loop to click "Load more" 48 times rapidly.
4.  Only start XHR interception and saving *after* the fast-forward is complete.

### C. The Catch-up Check (Early Exit)
For every batch of 50 intercepted:
1.  Check the first and last record of the batch against SQLite.
2.  If the record exists with the **exact same `Modified Date`**, we skip the update.
3.  If **all 50 records** in a batch are already perfectly synced, the scraper will stop and report "Database is fully up to date."

## 4. Live Insertion (`--insert`)
The command will now have two distinct phases: **Sync** and **Process**.

### Default Run (`php artisan import:bubble`)
- Scrapes data from web.
- Upserts records into `bubble_staging.sqlite`.
- Does **not** touch the application database (`users`, `caregivers`, etc.).

### Live Run (`php artisan import:bubble --insert`)
- Scrapes and saves to SQLite.
- Immediately passes each batch to `processHits()`.
- Records are mapped and saved to the main MySQL/PostgreSQL database as the download happens.

## 5. Implementation Roadmap
1.  **DB Utility:** Create methods to manage the SQLite connection and migrations.
2.  **Sort & Filter Logic:** Implement the browser interactions for sorting.
3.  **The Fast-Forwarder:** Implement the automated "Load more" loop.
4.  **The Sync Loop:** Update the XHR handler to use `INSERT OR REPLACE`.
5.  **Refactor handle():** Branch logic based on the `--insert` flag.

## 6. Verification Steps
- **Interruption Test:** Start a scrape, stop at 10%, restart, and verify it "fast-forwards" and finishes at 100%.
- **Duplicate Test:** Run twice and verify SQLite doesn't grow in size.
- **Update Test:** Modify a record on Bubble, run sync, and verify the `modified_at` timestamp updates in SQLite.
