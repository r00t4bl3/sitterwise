<?php

use App\Mail\AdminCaregiverArchivedMail;
use App\Mail\AdminCaregiverBackedOutMail;
use App\Mail\AdminNewApplicationMail;
use App\Mail\CaregiverArchiveWarningMail;
use App\Mail\CaregiverOnHoldCheckinMail;
use App\Mail\ReferenceCompletedMail;
use App\Mail\ReferenceFinalReminderMail;
use App\Mail\ReferenceReminderMail;
use App\Mail\ReferenceRequestMail;
use App\Models\ReferenceRequest;
use Illuminate\Mail\Mailable;

function referenceCompletedMailStub(): ReferenceCompletedMail
{
    $reference = new ReferenceRequest;
    $reference->reference_name = 'John Reference';

    return new ReferenceCompletedMail($reference, 'Jane Doe');
}

describe('Reference emails → branded SendGrid template', function () {
    test('reference request', function () {
        $payload = captureSendGridPayload(new ReferenceRequestMail('John Reference', 'Jane Doe', 'tok-1'));

        expect($payload['template_id'])->toBe('d-0533743f636141fe880c9bbe8097b084');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'reference_name' => 'John Reference',
            'applicant_name' => 'Jane Doe',
            'reference_url' => route('references.show', 'tok-1'),
        ]);
    });

    test('reference reminder', function () {
        $payload = captureSendGridPayload(new ReferenceReminderMail('John Reference', 'Jane Doe', 'tok-1'));

        expect($payload['template_id'])->toBe('d-0ca264e3ff9140f5be97765b372f6846');
        expect($payload['personalizations'][0]['dynamic_template_data']['reference_url'])
            ->toBe(route('references.show', 'tok-1'));
    });

    test('reference final reminder', function () {
        $payload = captureSendGridPayload(new ReferenceFinalReminderMail('John Reference', 'Jane Doe', 'tok-1'));

        expect($payload['template_id'])->toBe('d-5edba720ef7b4aec8a8b3d70a4dc2cbd');
    });

    test('reference completed (admin) — data + no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);

        $payload = captureSendGridPayload(referenceCompletedMailStub());

        expect($payload['template_id'])->toBe('d-622707caa2b54456a6921f032fb1af3e');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'applicant_name' => 'Jane Doe',
            'reference_name' => 'John Reference',
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });
});

describe('Admin + lifecycle emails → branded SendGrid template', function () {
    test('admin new application (admin) — no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);

        $payload = captureSendGridPayload(new AdminNewApplicationMail('Jane Doe', 'jane@example.com', 5));

        expect($payload['template_id'])->toBe('d-15f3364a4b4f493a9caa6e7031d96685');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'applicant_name' => 'Jane Doe',
            'applicant_email' => 'jane@example.com',
            'application_url' => route('applications.show', 5),
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });

    test('caregiver archive warning', function () {
        $payload = captureSendGridPayload(new CaregiverArchiveWarningMail('Carla Sitter', 30));

        expect($payload['template_id'])->toBe('d-6a7ef80cc2b74e978c38d6c1ea897846');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'caregiver_name' => 'Carla Sitter',
            'days_on_hold' => 30,
            'pause_settings_url' => url('/settings/caregiver/pause'),
        ]);
    });

    test('on-hold check-in — is_final / is_reminder reflect the tier', function () {
        $final = captureSendGridPayload(new CaregiverOnHoldCheckinMail('Carla Sitter', 30, 'final'));
        expect($final['template_id'])->toBe('d-4de573218a71436d849f2c67a6d9e6e7');
        expect($final['personalizations'][0]['dynamic_template_data'])
            ->toMatchArray(['is_final' => true, 'is_reminder' => false]);

        $reminder = captureSendGridPayload(new CaregiverOnHoldCheckinMail('Carla Sitter', 14, 'reminder'));
        expect($reminder['personalizations'][0]['dynamic_template_data'])
            ->toMatchArray(['is_final' => false, 'is_reminder' => true]);

        $checkin = captureSendGridPayload(new CaregiverOnHoldCheckinMail('Carla Sitter', 7, 'checkin'));
        expect($checkin['personalizations'][0]['dynamic_template_data'])
            ->toMatchArray(['is_final' => false, 'is_reminder' => false]);
    });

    test('admin caregiver archived (admin) — no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);

        $payload = captureSendGridPayload(new AdminCaregiverArchivedMail('Carla Sitter', 7, 30));

        expect($payload['template_id'])->toBe('d-6c385f3b5a5f4e5180ccee4fedc09106');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'caregiver_name' => 'Carla Sitter',
            'days_on_hold' => 30,
            'caregiver_url' => url('/caregivers/7'),
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });

    test('admin caregiver backed out (admin) — no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);

        $payload = captureSendGridPayload(new AdminCaregiverBackedOutMail('Carla Sitter', 7, 4512, 'Family emergency'));

        expect($payload['template_id'])->toBe('d-44ad02d6c50343709900263b8d1c3b28');
        expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
            'booking_id' => 4512,
            'caregiver_name' => 'Carla Sitter',
            'reason' => 'Family emergency',
            'jobs_url' => url('/caregivers/7/jobs'),
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });
});

describe('Team BCC applies to caregiver/applicant-facing emails but not admin ones', function () {
    test('a reference request (recipient-facing) gets the team BCC when configured', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);

        $payload = captureSendGridPayload(new ReferenceRequestMail('John Reference', 'Jane Doe', 'tok-1'));

        expect($payload['personalizations'][0]['bcc'][0]['email'])->toBe('hello@sitterwise.com');
    });
});

describe('Reference + lifecycle emails still render the Blade body locally', function () {
    test('each mailable renders without error', function (Mailable $mailable) {
        expect(config('mail.default'))->not->toBe('sendgrid');

        expect($mailable->render())->toBeString()->not->toBe('')->toContain('<');
    })->with([
        'reference request' => fn () => new ReferenceRequestMail('John Reference', 'Jane Doe', 'tok-1'),
        'reference reminder' => fn () => new ReferenceReminderMail('John Reference', 'Jane Doe', 'tok-1'),
        'reference final reminder' => fn () => new ReferenceFinalReminderMail('John Reference', 'Jane Doe', 'tok-1'),
        'reference completed' => fn () => referenceCompletedMailStub(),
        'admin new application' => fn () => new AdminNewApplicationMail('Jane Doe', 'jane@example.com', 5),
        'caregiver archive warning' => fn () => new CaregiverArchiveWarningMail('Carla Sitter', 30),
        'on-hold check-in' => fn () => new CaregiverOnHoldCheckinMail('Carla Sitter', 30, 'final'),
        'admin caregiver archived' => fn () => new AdminCaregiverArchivedMail('Carla Sitter', 7, 30),
        'admin caregiver backed out' => fn () => new AdminCaregiverBackedOutMail('Carla Sitter', 7, 4512, 'Family emergency'),
    ]);
});
