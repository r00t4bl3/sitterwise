<?php

use App\Enums\CaregiverStatus;
use App\Http\Controllers\BroadcastSmsController;
use App\Jobs\SendBroadcastMessage;
use App\Models\BroadcastMessage;
use App\Models\Caregiver;
use App\Models\SmsBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function broadcastSmsTestCaregiver(array $overrides = []): Caregiver
{
    $status = CaregiverStatus::tryFrom($overrides['status'] ?? 'active') ?? CaregiverStatus::Active;
    $user = User::factory()->create(['role' => 'caregiver']);

    return Caregiver::create([
        'user_id' => $user->id,
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName(),
        'phone' => $overrides['phone'] ?? fake()->phoneNumber(),
        'status' => $status->value,
        'sms_opted_out' => $overrides['sms_opted_out'] ?? false,
    ]);
}

function superAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

it('denies access for non-admin users', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('broadcast-sms.index'))
        ->assertForbidden();
})->with(['caregiver', 'client']);

it('allows access for admin users', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('broadcast-sms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/broadcast-sms/index')
        );
});

it('denies access for unauthenticated users', function () {
    $this->get(route('broadcast-sms.index'))
        ->assertRedirect('/login');
});

it('shows the broadcast page with recipient count for super admin', function () {
    broadcastSmsTestCaregiver();
    broadcastSmsTestCaregiver();
    broadcastSmsTestCaregiver();

    $this->actingAs(superAdmin())
        ->get(route('broadcast-sms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/broadcast-sms/index')
            ->where('recipientCount', 3)
            ->where('complianceFooter', BroadcastSmsController::COMPLIANCE_FOOTER)
        );
});

it('excludes opted-out caregivers from recipient count', function () {
    broadcastSmsTestCaregiver();
    broadcastSmsTestCaregiver();
    broadcastSmsTestCaregiver(['sms_opted_out' => true]);

    $this->actingAs(superAdmin())
        ->get(route('broadcast-sms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('recipientCount', 2)
        );
});

it('excludes inactive caregivers from recipient count', function () {
    broadcastSmsTestCaregiver(['status' => 'active']);
    broadcastSmsTestCaregiver(['status' => 'inactive']);

    $this->actingAs(superAdmin())
        ->get(route('broadcast-sms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('recipientCount', 1)
        );
});

it('excludes caregivers without a phone number', function () {
    broadcastSmsTestCaregiver();
    broadcastSmsTestCaregiver(['phone' => '']);

    $this->actingAs(superAdmin())
        ->get(route('broadcast-sms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('recipientCount', 1)
        );
});

it('creates broadcast and dispatches jobs on send', function () {
    Queue::fake();

    $caregivers = [];
    $caregivers[] = broadcastSmsTestCaregiver();
    $caregivers[] = broadcastSmsTestCaregiver();
    $caregivers[] = broadcastSmsTestCaregiver();

    $user = superAdmin();

    $response = $this->actingAs($user)
        ->post(route('broadcast-sms.store'), [
            'message_body' => 'Test broadcast message',
        ]);

    $response->assertRedirect();

    $broadcast = SmsBroadcast::first();
    expect($broadcast)->not->toBeNull();
    expect($broadcast->sent_by_user_id)->toBe($user->id);
    expect($broadcast->recipient_count)->toBe(3);
    // Twilio appends the STOP opt-out disclosure, so our stored body ends with
    // just the pause nudge (no duplicate STOP language).
    expect($broadcast->message_body)->toEndWith('Pause your account to stop.')
        ->and($broadcast->message_body)->not->toContain('Reply STOP');

    expect(BroadcastMessage::count())->toBe(3);

    foreach ($caregivers as $caregiver) {
        expect(BroadcastMessage::where('caregiver_id', $caregiver->id)->exists())->toBeTrue();
    }

    Queue::assertPushed(SendBroadcastMessage::class, 3);
});

it('admin can send broadcast message', function () {
    Queue::fake();
    broadcastSmsTestCaregiver();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('broadcast-sms.store'), [
            'message_body' => 'Admin broadcast test',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(SendBroadcastMessage::class, 1);
});

it('denies post access for non-admin users', function (string $role) {
    Queue::fake();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->post(route('broadcast-sms.store'), [
            'message_body' => 'Test',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
})->with(['caregiver', 'client']);

it('validates message body is required', function () {
    $this->actingAs(superAdmin())
        ->post(route('broadcast-sms.store'), [
            'message_body' => '',
        ])
        ->assertSessionHasErrors('message_body');
});

it('validates message body does not exceed max length', function () {
    $this->actingAs(superAdmin())
        ->post(route('broadcast-sms.store'), [
            'message_body' => str_repeat('a', 919),
        ])
        ->assertSessionHasErrors('message_body');
});

it('returns error when no eligible caregivers exist', function () {
    broadcastSmsTestCaregiver(['status' => 'inactive']);

    $this->actingAs(superAdmin())
        ->post(route('broadcast-sms.store'), [
            'message_body' => 'Test message',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'No eligible caregivers found.');
});

it('handles STOP inbound sms and opts out caregiver', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'STOP',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles STOPALL inbound sms', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '(619) 555-1212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'STOPALL',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles case-insensitive stop keywords', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'stop',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('ignores non-stop messages', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'Hello',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeFalse();
});

it('handles STOP from unknown number gracefully', function () {
    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+19999999999',
        'Body' => 'STOP',
    ])->assertOk();
});

it('matches phone numbers with different formatting', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '619-555-1212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'STOP',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles UNSUBSCRIBE keyword', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'UNSUBSCRIBE',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles CANCEL keyword', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'CANCEL',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles END keyword', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'END',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('handles QUIT keyword', function () {
    $caregiver = broadcastSmsTestCaregiver(['phone' => '+16195551212']);

    $this->post(route('webhooks.twilio.inbound'), [
        'From' => '+16195551212',
        'Body' => 'QUIT',
    ])->assertOk();

    $caregiver->refresh();
    expect($caregiver->sms_opted_out)->toBeTrue();
});

it('updates broadcast message status via callback', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $broadcast = SmsBroadcast::create([
        'sent_by_user_id' => $user->id,
        'message_body' => 'Test',
        'recipient_count' => 1,
    ]);

    $caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+1234567890',
        'status' => CaregiverStatus::Active->value,
    ]);

    $broadcastMessage = BroadcastMessage::create([
        'broadcast_id' => $broadcast->id,
        'caregiver_id' => $caregiver->id,
        'phone_number' => '+1234567890',
        'message_body' => 'Test',
        'status' => 'sent',
        'twilio_message_sid' => 'SM123456789',
    ]);

    $this->post(route('webhooks.twilio.status'), [
        'MessageSid' => 'SM123456789',
        'MessageStatus' => 'delivered',
    ])->assertOk();

    $broadcastMessage->refresh();
    expect($broadcastMessage->status)->toBe('delivered');
});
