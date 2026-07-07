<?php

use App\Mail\ApplicantConfirmationMail;
use App\Mail\ApplicantDeclinedMail;
use App\Mail\ApplicantFinalReminderMail;
use App\Mail\ApplicantHiredMail;
use App\Mail\ApplicantPendingReferencesMail;
use App\Mail\ApplicantResumeApplicationMail;
use Illuminate\Mail\Mailable;

describe('Applicant emails — production sends the branded SendGrid template', function () {
    test('confirmation', function () {
        $payload = captureSendGridPayload(new ApplicantConfirmationMail('Jane Doe', 'tok-123'));

        expect($payload['template_id'])->toBe('d-46445a000ef24dc690dc7eda3f438f1e');
        expect($payload['personalizations'][0]['dynamic_template_data'])
            ->toBe([
                'applicant_name' => 'Jane Doe',
                'status_url' => url('/caregiver/apply/status/tok-123'),
            ]);
    });

    test('resume application', function () {
        $payload = captureSendGridPayload(new ApplicantResumeApplicationMail('jane@example.com'));

        expect($payload['template_id'])->toBe('d-4cf619b4ce1e4b62b1508de56f6a1069');
        expect($payload['personalizations'][0]['dynamic_template_data'])
            ->toBe(['apply_url' => url('/caregiver/apply')]);
    });

    test('final reminder', function () {
        $payload = captureSendGridPayload(new ApplicantFinalReminderMail('jane@example.com'));

        expect($payload['template_id'])->toBe('d-33fa38edec7f4b2cb39b78d2ab652c9f');
        expect($payload['personalizations'][0]['dynamic_template_data'])
            ->toBe(['apply_url' => url('/caregiver/apply')]);
    });

    test('pending references — over_one_week boolean flips at 7 days', function () {
        $under = captureSendGridPayload(new ApplicantPendingReferencesMail('Jane Doe', 3));
        expect($under['template_id'])->toBe('d-eaa36d01d9e948849e15e2afadb8b71d');
        expect($under['personalizations'][0]['dynamic_template_data'])
            ->toBe(['applicant_name' => 'Jane Doe', 'over_one_week' => false]);

        $over = captureSendGridPayload(new ApplicantPendingReferencesMail('Jane Doe', 9));
        expect($over['personalizations'][0]['dynamic_template_data']['over_one_week'])->toBeTrue();
    });

    test('hired', function () {
        $payload = captureSendGridPayload(new ApplicantHiredMail('Jane Doe', 'https://sitterwise.test/status'));

        expect($payload['template_id'])->toBe('d-4ff3875d2aab4fd293662eabb8aa6e77');
        expect($payload['personalizations'][0]['dynamic_template_data'])
            ->toBe(['applicant_name' => 'Jane Doe', 'status_url' => 'https://sitterwise.test/status']);
    });

    test('declined — reason key is omitted when null, present when given', function () {
        $without = captureSendGridPayload(new ApplicantDeclinedMail('Jane Doe'));
        expect($without['template_id'])->toBe('d-fbfcb36f2d69474eb764f82ad1dac84b');
        expect($without['personalizations'][0]['dynamic_template_data'])->toBe(['applicant_name' => 'Jane Doe']);

        $with = captureSendGridPayload(new ApplicantDeclinedMail('Jane Doe', 'Incomplete references'));
        expect($with['personalizations'][0]['dynamic_template_data'])
            ->toBe(['applicant_name' => 'Jane Doe', 'reason' => 'Incomplete references']);
    });
});

describe('Applicant emails — envelope conventions', function () {
    test('from carries the Sitterwise sender name', function () {
        $payload = captureSendGridPayload(new ApplicantConfirmationMail('Jane Doe', 'tok-123'));

        expect($payload['from']['email'])->toBe(config('mail.from.address'));
        expect($payload['from']['name'])->toBe(config('mail.from.name'));
    });

    test('team BCC is off by default and applied when configured', function () {
        expect(config('mail.team_bcc'))->toBeNull();
        $off = captureSendGridPayload(new ApplicantConfirmationMail('Jane Doe', 'tok-123'));
        expect($off['personalizations'][0])->not->toHaveKey('bcc');

        config(['mail.team_bcc' => 'hello@sitterwise.com']);
        $on = captureSendGridPayload(new ApplicantConfirmationMail('Jane Doe', 'tok-123'));
        expect($on['personalizations'][0]['bcc'][0]['email'])->toBe('hello@sitterwise.com');
    });
});

describe('Applicant emails — local/test still render the Blade body', function () {
    test('each mailable renders its Blade view without error', function (Mailable $mailable) {
        expect(config('mail.default'))->not->toBe('sendgrid');

        $rendered = $mailable->render();

        expect($rendered)->toBeString()->not->toBe('')->toContain('<');
    })->with([
        'confirmation' => fn () => new ApplicantConfirmationMail('Jane Doe', 'tok-123'),
        'resume' => fn () => new ApplicantResumeApplicationMail('jane@example.com'),
        'final reminder' => fn () => new ApplicantFinalReminderMail('jane@example.com'),
        'pending references' => fn () => new ApplicantPendingReferencesMail('Jane Doe', 9),
        'hired' => fn () => new ApplicantHiredMail('Jane Doe', 'https://sitterwise.test/status'),
        'declined' => fn () => new ApplicantDeclinedMail('Jane Doe', 'Incomplete references'),
    ]);
});
