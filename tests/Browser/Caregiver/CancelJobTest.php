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

test('caregiver can cancel a confirmed job', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $start = now()->addDay()->startOfDay()->addHours(9);
    $end = (clone $start)->addHours(4);

    $booking = Booking::factory()->forClient($client)->create([
        'start_datetime' => $start,
        'end_datetime' => $end,
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

    $page->assertSee('Cancel Job');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const cancelBtn = buttons.find(b => b.textContent.includes('Cancel Job'));
        if (cancelBtn) cancelBtn.click();
    JS);

    $page->waitForText('Reason for cancellation', 5);

    fillTextarea($page, 'Briefly tell us', 'Test cancellation reason');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const submitBtn = buttons.find(b => b.textContent.includes('Cancel this job'));
        if (submitBtn) submitBtn.click();
    JS);

    $page->assertNoJavaScriptErrors();
});
