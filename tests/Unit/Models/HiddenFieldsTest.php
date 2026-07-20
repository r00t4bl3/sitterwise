<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('hides secret tokens and staff-internal fields when a caregiver is serialized', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Hidden',
        'last_name' => 'Fields',
        'slug' => 'hidden-fields-'.Str::random(4),
        'phone' => '555-0300',
        'date_of_birth' => '1990-01-01',
        'status_token' => 'secret-status-token',
        'calendar_feed_token' => 'secret-feed-token',
        'stripe_account_id' => 'acct_secret',
        'admin_rating' => 4,
        'notes' => 'INTERNAL staff note',
    ]);

    $array = $caregiver->fresh()->toArray();

    expect($array)->not->toHaveKeys([
        'status_token',
        'calendar_feed_token',
        'stripe_account_id',
        'admin_rating',
        'notes',
    ]);

    // Still readable via explicit property access (how admin surfaces read them).
    expect($caregiver->fresh()->stripe_account_id)->toBe('acct_secret')
        ->and($caregiver->fresh()->notes)->toBe('INTERNAL staff note');
});

it('hides notes and the stripe customer id when a client is serialized', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create([
        'user_id' => $user->id,
        'notes' => 'INTERNAL client note',
        'stripe_customer_id' => 'cus_secret',
    ]);

    $array = $client->fresh()->toArray();

    expect($array)->not->toHaveKeys(['notes', 'stripe_customer_id']);

    expect($client->fresh()->stripe_customer_id)->toBe('cus_secret')
        ->and($client->fresh()->notes)->toBe('INTERNAL client note');
});
