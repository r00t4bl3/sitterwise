# Sitterwise: Database Migrations Consolidation Plan

This document provides a detailed technical blueprint to squash and merge incremental database migration files into their base tables. This clean-up is ideal during the active development phase to prevent schema bloat and keep the `database/migrations` directory clean and maintainable.

---

## 1. Executive Summary

Instead of running a long list of incremental migrations that add columns or modify tables, we will merge all additions directly into each table's original `create_..._table` migration file.

### Objectives:
*   Consolidate all schema changes so there is **exactly one** migration file defining the complete structure of each table.
*   Safely delete **15 redundant incremental files** and **1 orphaned base create migration**.
*   Verify that `php artisan migrate:fresh --seed` builds the identical target database schema cleanly from scratch.

---

## 2. Table-by-Table Consolidation Map

Here is the exact mapping of which incremental migrations will be squashed and merged into their corresponding base tables.

### 1. `users` Table
*   **Base Migration File**: `0001_01_01_000000_create_users_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2025_08_14_170933_add_two_factor_columns_to_users_table.php`
    *   `2026_03_24_184940_add_role_to_users_table.php`
    *   `2026_05_10_160152_add_bubble_id_to_multiple_tables.php` *(only the `users` section)*
*   **Merged Blueprint Result**:
    ```php
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->enum('role', ['caregiver', 'client', 'admin', 'super_admin'])->default('client');
        $table->string('profile_photo_path')->nullable();
        $table->string('profile_photo_url')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->string('bubble_id')->nullable()->index();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });
    ```

### 2. `bookings` Table
*   **Base Migration File**: `2026_04_01_041859_create_bookings_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_10_162723_add_cancellation_fields_to_bookings_table.php`
    *   `2026_05_19_012216_add_children_notes_to_bookings_table.php`
    *   `2026_05_10_160152_add_bubble_id_to_multiple_tables.php` *(only the `bookings` section)*
*   **New column (audit trail)**:
    *   `cancelled_by_id` — nullable FK to `users`
*   **Merged Blueprint Result**:
    Add these columns directly inside the original `bookings` table blueprint callback:
    ```php
    $table->text('children_notes')->nullable();
    $table->string('bubble_id')->nullable()->index();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancellation_reason')->nullable();
    $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->onDelete('set null');
    ```

### 3. `caregivers` Table
*   **Base Migration File**: `2026_03_24_174641_create_caregivers_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_19_022455_add_sms_opted_out_to_caregivers_table.php`
    *   `2026_05_24_112710_add_status_token_to_caregivers.php`
    *   `2026_05_23_033133_ensure_caregivers_status_column_exists.php`
    *   `2026_05_23_033641_fix_caregiver_status_case.php`
    *   `2026_05_10_160152_add_bubble_id_to_multiple_tables.php` *(only the `caregivers` section)*
*   **Base Files to Delete (table no longer used)**:
    *   `2026_03_24_174616_create_caregiver_statuses_table.php`
*   **Additional Incremental to Delete (redundant)**:
    *   `2026_05_23_014146_cleanup_caregiver_statuses_name_format.php`
*   **Merged Blueprint Result**:
    Replace `status_id` FK with a string `status` column and add the remaining columns:
    ```php
    /* Replace this: */
    // $table->foreignId('status_id')->constrained('caregiver_statuses');

    /* With these: */
    $table->string('status')->default('applied');
    $table->string('bubble_id')->nullable()->index();
    $table->boolean('sms_opted_out')->default(false);
    $table->string('status_token', 64)->nullable()->unique();
    ```

### 4. `clients` Table
*   **Base Migration File**: `2026_03_28_191335_create_clients_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_10_181816_add_last_booking_date_to_clients_table.php`
    *   `2026_05_10_160152_add_bubble_id_to_multiple_tables.php` *(only the `clients` section)*
*   **Merged Blueprint Result**:
    Add these columns directly inside the original `clients` table blueprint callback:
    ```php
    $table->string('bubble_id')->nullable()->index();
    $table->date('last_booking_date')->nullable();
    ```

### 5. `reference_requests` Table
*   **Base Migration File**: `2026_05_19_171503_create_reference_requests_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_24_093321_add_rating_categories_to_reference_requests.php`
*   **Merged Blueprint Result**:
    Replace the single `rating`/`feedback` columns with the expanded rating categories. Do NOT include the old `rating` or `feedback` columns:
    ```php
    $table->unsignedTinyInteger('rating_reliability')->nullable();
    $table->unsignedTinyInteger('rating_trustworthiness')->nullable();
    $table->unsignedTinyInteger('rating_maturity')->nullable();
    $table->unsignedTinyInteger('rating_communication')->nullable();
    $table->unsignedTinyInteger('rating_warmth')->nullable();
    $table->unsignedTinyInteger('rating_overall_recommendation')->nullable();
    $table->text('strengths')->nullable();
    $table->text('concerns')->nullable();
    $table->text('additional_comments')->nullable();
    ```

### 6. `booking_ratings` Table
*   **Base Migration File**: `2026_04_18_003207_create_booking_ratings_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_10_170611_add_bubble_id_to_booking_ratings_table.php`
*   **Merged Blueprint Result**:
    Add `bubble_id` directly to the `booking_ratings` table:
    ```php
    $table->string('bubble_id')->nullable()->index();
    ```

### 7. `caregiver_educations` Table
*   **Base Migration File**: `2026_04_13_185509_create_caregiver_educations_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_23_164318_add_degree_and_expand_education_type_to_caregiver_educations.php`
*   **Merged Blueprint Result**:
    Change `education_type` from enum to string and add `degree`:
    ```php
    $table->string('education_type'); /* was: enum('high_school', 'college') */
    $table->string('degree')->nullable();
    ```

### 8. `specialty_types` Table
*   **Base Migration File**: `2026_03_24_174708_create_specialty_types_table.php`
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_23_160220_add_colors_and_remove_special_needs_from_specialty_types.php`
*   **Merged Blueprint Result**:
    Add color fields to the base table. The "Special Needs" delete is a no-op on fresh migration (the seeder never creates it):
    ```php
    $table->string('color_bg', 7)->nullable();
    $table->string('color_border', 7)->nullable();
    $table->string('color_text', 7)->nullable();
    ```

### 9. `client_payments` & `caregiver_payouts` Tables
*   **Incremental Files to Merge & Delete**:
    *   `2026_05_10_171508_add_bubble_id_to_financial_tables.php`
*   **Merged Blueprint Result**:
    *   In base `2026_04_09_101904_create_client_payments_table.php`, add:
        ```php
        $table->string('bubble_id')->nullable()->index();
        ```
    *   In base `2026_04_10_081415_create_caregiver_payouts_table.php`, add:
        ```php
        $table->foreignId('booking_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        $table->string('bubble_id')->nullable()->index();
        ```

---

## 3. Files to Delete (Complete List)

### Incremental Migration Files (15 files)
1. `2025_08_14_170933_add_two_factor_columns_to_users_table.php`
2. `2026_03_24_184940_add_role_to_users_table.php`
3. `2026_05_10_160152_add_bubble_id_to_multiple_tables.php`
4. `2026_05_10_162723_add_cancellation_fields_to_bookings_table.php`
5. `2026_05_19_012216_add_children_notes_to_bookings_table.php`
6. `2026_05_19_022455_add_sms_opted_out_to_caregivers_table.php`
7. `2026_05_24_112710_add_status_token_to_caregivers.php`
8. `2026_05_23_033133_ensure_caregivers_status_column_exists.php`
9. `2026_05_23_033641_fix_caregiver_status_case.php`
10. `2026_05_23_014146_cleanup_caregiver_statuses_name_format.php`
11. `2026_05_10_181816_add_last_booking_date_to_clients_table.php`
12. `2026_05_24_093321_add_rating_categories_to_reference_requests.php`
13. `2026_05_10_170611_add_bubble_id_to_booking_ratings_table.php`
14. `2026_05_23_164318_add_degree_and_expand_education_type_to_caregiver_educations.php`
15. `2026_05_23_160220_add_colors_and_remove_special_needs_from_specialty_types.php`
16. `2026_05_10_171508_add_bubble_id_to_financial_tables.php`

### Base Migration Files to Delete (1 file)
17. `2026_03_24_174616_create_caregiver_statuses_table.php` (replaced by string `status` column)

---

## 4. Recommended Execution Steps

1.  **Backup/Commit First**:
    Ensure all current work is fully committed to Git before deleting or modifying files:
    ```bash
    git add .
    git commit -m "chore: save state before migration consolidation"
    ```
2.  **Consolidate in Editor**:
    Open each base file listed above and add the columns directly to the Schema Blueprint block.
3.  **Delete Files**:
    Remove all 17 migration files listed above.
4.  **Rebuild Database & Run Seeders**:
    Execute fresh migration to construct the complete database from scratch and verify everything is working:
    ```bash
    php artisan migrate:fresh --seed
    ```
5.  **Verify Test Suite**:
    Run Pest feature tests to verify that the application operates correctly on the fresh schema:
    ```bash
    php artisan test --compact
    ```
