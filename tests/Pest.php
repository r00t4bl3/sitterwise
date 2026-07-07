<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Sichikawa\LaravelSendgridDriver\Transport\SendgridTransport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Send a mailable through the real SendGrid transport (with a mocked HTTP
 * client) and return the JSON payload that would be POSTed to the SendGrid API.
 * Used to assert branded-template migrations without hitting SendGrid.
 *
 * @return array<string, mixed>
 */
function captureSendGridPayload(Mailable $mailable, string $to = 'recipient@example.com'): array
{
    config(['mail.default' => 'sendgrid']);

    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(202, ['X-Message-Id' => 'test-message-id']),
    ]));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);

    Mail::extend('sendgrid', fn () => new SendgridTransport($client, 'fake-api-key'));
    app('mail.manager')->purge('sendgrid');

    Mail::to($to)->sendNow($mailable);

    return json_decode((string) $history[0]['request']->getBody(), true);
}
