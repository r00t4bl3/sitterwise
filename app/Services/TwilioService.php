<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    private ?Client $client = null;

    private ?string $fromNumber = null;

    private ?string $messagingServiceSid = null;

    public function __construct()
    {
        $this->fromNumber = config('services.twilio.phone_number');
        $this->messagingServiceSid = config('services.twilio.messaging_service_sid');
    }

    public function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token')
            );
        }

        return $this->client;
    }

    public function send(string $to, string $message, array $options = []): array
    {
        $payload = array_merge([
            'messagingServiceSid' => $this->messagingServiceSid,
            'body' => $message,
        ], $options);

        $result = $this->client()->messages->create($to, $payload);

        return [
            'success' => true,
            'sid' => $result->sid,
            'to' => $to,
            'from' => $this->fromNumber,
            'status' => $result->status,
        ];
    }

    public function sendDryRun(string $to, string $message): array
    {
        return [
            'success' => true,
            'dry_run' => true,
            'to' => $to,
            'from' => $this->fromNumber,
            'message' => $message,
        ];
    }
}
