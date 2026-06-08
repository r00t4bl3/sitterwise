<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;

function setNativeValueJs(): string
{
    return <<<'JS'
        const setNativeValue = (el, val) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };
    JS;
}

function fillField($page, string $selector, string $value): void
{
    $escapedSelector = addslashes($selector);
    $escapedValue = addslashes($value);
    $page->script(<<<JS
        const el = document.querySelector('{$escapedSelector}');
        if (el) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, '{$escapedValue}');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    JS);
}

function fillTextarea($page, string $placeholder, string $value): void
{
    $escapedPlaceholder = addslashes($placeholder);
    $escapedValue = addslashes($value);
    $page->script(<<<JS
        const el = document.querySelector('textarea[placeholder*="{$escapedPlaceholder}"]');
        if (el) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            setter.call(el, '{$escapedValue}');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    JS);
}

function clickElement($page, string $selector): void
{
    $escapedSelector = addslashes($selector);
    $page->script(<<<JS
        const el = document.querySelector('{$escapedSelector}');
        if (el) el.click();
    JS);
}

function createClientUser(): User
{
    $user = User::factory()->create();

    Client::factory()->create(['user_id' => $user->id]);

    return $user;
}

function createCaregiver(): User
{
    $user = User::factory()->create(['role' => 'caregiver']);

    Caregiver::create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-test',
        'phone' => '555-123-4567',
        'address_line1' => '123 Main St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '1990-01-01',
        'status' => 'active',
    ]);

    return $user;
}

function selectOption($page, string $triggerSelector, string $optionText): void
{
    clickElement($page, $triggerSelector);

    usleep(200000);

    $escapedText = addslashes($optionText);
    $page->script(<<<JS
        const options = Array.from(document.querySelectorAll('[role="option"]'));
        const match = options.find(el => el.textContent.trim() === '{$escapedText}');
        if (match) match.click();
    JS);
}

function createCompletedBooking(): array
{
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->completed()->create();
    $booking->load('bookingGroup.bookings', 'caregiver.user', 'client');
    $caregiver = $booking->caregiver;
    $caregiverUser = $caregiver->user;

    return [$booking, $client, $caregiver, $clientUser, $caregiverUser];
}

function selectOptionByLabel($page, string $labelText, string $optionText): void
{
    $escapedLabel = addslashes($labelText);
    $escapedOption = addslashes($optionText);
    $page->script(<<<JS
        const labels = Array.from(document.querySelectorAll('label'));
        const label = labels.find(l => l.textContent.trim().includes('{$escapedLabel}'));
        if (label) {
            const section = label.closest('div');
            const trigger = section ? section.querySelector('button[role="combobox"]') : null;
            if (trigger) trigger.click();
        }
    JS);

    usleep(200000);

    $page->script(<<<JS
        const options = Array.from(document.querySelectorAll('[role="option"]'));
        const match = options.find(el => el.textContent.trim() === '{$escapedOption}');
        if (match) match.click();
    JS);
}

function submitGuestBookingForm($page): void
{
    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const submitBtn = buttons.find(b => b.textContent.includes('Continue to Payment'));
        if (submitBtn) submitBtn.click();
    JS);
}

function loginViaJs($page, string $email, string $password): void
{
    $escapedEmail = addslashes($email);
    $escapedPassword = addslashes($password);
    $page->script(<<<JS
        const email = document.querySelector('#email');
        const password = document.querySelector('#password');

        const setNativeValue2 = (el, val) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, val);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        setNativeValue2(email, '{$escapedEmail}');
        setNativeValue2(password, '{$escapedPassword}');
    JS);

    $page->script(<<<'JS'
        document.querySelector('button[type="submit"]').click();
    JS);
}
