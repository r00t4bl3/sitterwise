<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

test('production sends the branded password-reset template with first_name + reset_url', function () {
    // Even with a team BCC configured, a reset link must never be BCC'd.
    config(['mail.team_bcc' => 'hello@sitterwise.com']);

    $payload = captureSendGridPayload(
        new PasswordResetMail('Jane', 'https://sitterwise.test/reset-password/tok?email=jane@example.com'),
        'jane@example.com',
    );

    expect($payload['template_id'])->toBe('d-ed180932c2904c028fc5df6bd90a0c69');
    expect($payload['personalizations'][0]['dynamic_template_data'])->toBe([
        'first_name' => 'Jane',
        'reset_url' => 'https://sitterwise.test/reset-password/tok?email=jane@example.com',
    ]);
    expect($payload['personalizations'][0])->not->toHaveKey('bcc');
});

test('the reset email renders its Blade body locally with the link', function () {
    $rendered = (new PasswordResetMail('Jane', 'https://sitterwise.test/reset-abc'))->render();

    expect($rendered)
        ->toContain('Hi Jane')
        ->toContain('https://sitterwise.test/reset-abc');
});

test('the password-reset notification is routed to our mailable (Fortify wiring)', function () {
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    // toMailUsing is registered in FortifyServiceProvider::boot().
    $mail = (new ResetPassword('test-token'))->toMail($user);

    expect($mail)->toBeInstanceOf(PasswordResetMail::class);
    expect($mail->firstName)->toBe('Jane');
    expect($mail->resetUrl)->toContain('/reset-password/test-token');
    expect($mail->hasTo('jane@example.com'))->toBeTrue();
});
