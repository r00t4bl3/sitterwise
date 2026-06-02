<?php

use App\Enums\CaregiverStatus;
use App\Jobs\SendBroadcastMessage;
use App\Models\BroadcastMessage;
use App\Models\Caregiver;
use App\Models\SmsBroadcast;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
});

function smsBroadcastCaregiver(array $overrides = []): Caregiver
{
    return Caregiver::factory()->create(array_merge([
        'status' => CaregiverStatus::Active->value,
        'sms_opted_out' => false,
        'phone' => fake()->phoneNumber(),
    ], $overrides));
}

describe('SMS Broadcast Dispatch', function () {
    test('super admin can send broadcast and jobs are dispatched per eligible caregiver', function () {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $caregiver1 = smsBroadcastCaregiver();
        $caregiver2 = smsBroadcastCaregiver();

        actingAs($admin)
            ->post(route('broadcast-sms.store'), [
                'message_body' => 'Test broadcast message',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $broadcast = SmsBroadcast::first();
        expect($broadcast)->not->toBeNull();
        expect($broadcast->recipient_count)->toBeGreaterThanOrEqual(1);

        $messages = BroadcastMessage::where('broadcast_id', $broadcast->id)->get();
        expect($messages)->toHaveCount($broadcast->recipient_count);

        Queue::assertPushed(SendBroadcastMessage::class, $broadcast->recipient_count);
    });

    test('broadcast excludes opted-out caregivers', function () {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $caregiver1 = smsBroadcastCaregiver();
        smsBroadcastCaregiver(['sms_opted_out' => true]);

        actingAs($admin)
            ->post(route('broadcast-sms.store'), [
                'message_body' => 'Opt-out test',
            ]);

        $broadcast = SmsBroadcast::first();
        expect($broadcast->recipient_count)->toBe(1);

        Queue::assertPushed(SendBroadcastMessage::class, 1);
    });

    test('broadcast creates SmsBroadcast and BroadcastMessage records', function () {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $caregiver = smsBroadcastCaregiver();

        actingAs($admin)
            ->post(route('broadcast-sms.store'), [
                'message_body' => 'Record test',
            ]);

        $broadcast = SmsBroadcast::first();
        expect($broadcast)->not->toBeNull();
        expect($broadcast->sent_by_user_id)->toBe($admin->id);
        expect($broadcast->message_body)->toContain('Record test');

        $message = BroadcastMessage::where('broadcast_id', $broadcast->id)->first();
        expect($message)->not->toBeNull();
        expect($message->caregiver_id)->toBe($caregiver->id);
        expect($message->status)->toBe('queued');
        expect($message->twilio_message_sid)->toBeNull();
    });

    test('broadcast fails when no eligible caregivers', function () {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = actingAs($admin)
            ->post(route('broadcast-sms.store'), [
                'message_body' => 'No recipients',
            ]);

        $response->assertSessionHas('error');
    });
});
