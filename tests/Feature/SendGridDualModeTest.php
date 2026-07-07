<?php

use App\Mail\SendGridTemplate;
use App\Models\Booking;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Sichikawa\LaravelSendgridDriver\Transport\SendgridTransport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->booking = Booking::factory()->create();
});

describe('SendGrid dual-mode', function () {
    test('local/test (non-sendgrid mailer) renders the Blade body', function () {
        // The test suite runs with MAIL_MAILER=array, so sendgrid() is a no-op.
        expect(config('mail.default'))->not->toBe('sendgrid');

        $rendered = (new SendGridTemplate($this->booking))->render();

        expect($rendered)
            ->toContain('BLADE_BODY_RENDERED')
            ->toContain('Booking #'.$this->booking->id)
            ->toContain('Sitterwise SendGrid POC');
    });

    test('production (sendgrid mailer) sends the dynamic template with data + recipients', function () {
        config(['mail.default' => 'sendgrid']);

        // Capture the JSON that would be POSTed to the SendGrid API.
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(202, ['X-Message-Id' => 'test-message-id']),
        ]));
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        Mail::extend('sendgrid', fn () => new SendgridTransport($client, 'fake-api-key'));
        app('mail.manager')->purge('sendgrid');

        Mail::to('recipient@example.com')
            ->bcc('hello@sitterwise.com')
            ->send(new SendGridTemplate($this->booking));

        expect($history)->toHaveCount(1);
        $payload = json_decode((string) $history[0]['request']->getBody(), true);

        // The branded template + its data are what SendGrid will render.
        expect($payload['template_id'])->toBe('d-2a539fde38bb46788fc96baf7fb6366b');
        expect($payload['personalizations'][0])->toHaveKey('dynamic_template_data');
        expect($payload['personalizations'][0]['dynamic_template_data'])
            ->toHaveKey('booking_id', $this->booking->id);

        // Recipient + BCC (team copy) flow through to the SendGrid personalization.
        expect($payload['personalizations'][0]['to'][0]['email'])->toBe('recipient@example.com');
        expect($payload['personalizations'][0]['bcc'][0]['email'])->toBe('hello@sitterwise.com');
    });
});
