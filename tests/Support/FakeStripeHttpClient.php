<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, absUrl: string, headers: array, params: array}> */
    public array $requests = [];

    public function __construct(
        public ?string $body = null,
        public int $status = 200,
        public ?\Throwable $throws = null,
    ) {}

    public static function install(?self $client = null): self
    {
        config(['services.stripe.secret' => 'sk_test_fake']);
        $client ??= new self;
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    public static function reset(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests[] = compact('method', 'absUrl', 'headers', 'params');

        if ($this->throws) {
            throw $this->throws;
        }

        if ($this->body !== null) {
            return [$this->body, $this->status, []];
        }

        return [$this->defaultBodyFor($absUrl), $this->status, []];
    }

    protected function defaultBodyFor(string $absUrl): string
    {
        if (str_contains($absUrl, '/transfers')) {
            return '{"id":"tr_fake","object":"transfer"}';
        }

        if (str_contains($absUrl, '/customers')) {
            return '{"id":"cus_fake","object":"customer"}';
        }

        if (str_contains($absUrl, '/payment_methods')) {
            return '{"id":"pm_fake","object":"payment_method","card":{"brand":"visa","last4":"4242","exp_month":12,"exp_year":2030}}';
        }

        // Checkout sessions and setup intents must be their own object types with
        // a client_secret; falling through to the payment_intent body makes the
        // SDK build a PaymentIntent and emit an "Undefined property: client_secret"
        // notice when the caller reads $session->client_secret.
        if (str_contains($absUrl, '/checkout/sessions')) {
            return '{"id":"cs_fake","object":"checkout.session","client_secret":"cs_fake_secret","status":"open","setup_intent":"seti_fake"}';
        }

        if (str_contains($absUrl, '/setup_intents')) {
            return '{"id":"seti_fake","object":"setup_intent","client_secret":"seti_fake_secret","status":"succeeded","payment_method":"pm_fake"}';
        }

        return '{"id":"pi_fake","object":"payment_intent","status":"succeeded","client_secret":"pi_fake_secret"}';
    }

    /** @return array<int, ?string> */
    public function idempotencyKeys(): array
    {
        return collect($this->requests)
            ->map(fn (array $request) => collect($request['headers'])
                ->first(fn (string $header) => str_starts_with($header, 'Idempotency-Key:')))
            ->map(fn (?string $header) => $header ? trim(substr($header, strlen('Idempotency-Key:'))) : null)
            ->all();
    }
}
