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

        return '{"id":"pi_fake","object":"payment_intent","status":"succeeded"}';
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
