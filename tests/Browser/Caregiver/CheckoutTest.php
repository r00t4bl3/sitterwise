<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver can checkout a job', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $pastStart = now()->subHours(8);
    $pastEnd = now()->subHours(4);

    $booking = Booking::factory()->forClient($client)->create([
        'start_datetime' => $pastStart,
        'end_datetime' => $pastEnd,
        'status' => 'confirmed',
        'caregiver_id' => null,
    ]);

    $caregiverUser = createCaregiver();
    $booking->update(['caregiver_id' => $caregiverUser->caregiver->id]);
    $booking->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiverUser->caregiver->id],
        ['assigned_at' => now()],
    );

    $this->actingAs($caregiverUser);

    $page = visit('/jobs');

    $page->assertSee('Checkout');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const checkoutBtn = buttons.find(b => b.textContent.includes('Checkout'));
        if (checkoutBtn) checkoutBtn.click();
    JS);

    $page->waitForText('Ready for Checkout', 5);

    $startStr = $pastStart->format('Y-m-d\TH:i');
    $endStr = $pastEnd->format('Y-m-d\TH:i');

    fillField($page, 'input[placeholder*="Start date"]', $startStr);
    fillField($page, 'input[placeholder*="End date"]', $endStr);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const submitBtn = buttons.find(b => b.textContent.includes('Submit Checkout'));
        if (submitBtn) submitBtn.click();
    JS);

    $page->assertNoJavaScriptErrors();
});
