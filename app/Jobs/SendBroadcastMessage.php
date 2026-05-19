<?php

namespace App\Jobs;

use App\Models\BroadcastMessage;
use App\Services\TwilioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBroadcastMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public BroadcastMessage $broadcastMessage
    ) {}

    public function handle(TwilioService $twilio): void
    {
        if ($this->broadcastMessage->status !== 'queued') {
            return;
        }

        $statusCallback = route('webhooks.twilio.status');

        $result = $twilio->send(
            $this->broadcastMessage->phone_number,
            $this->broadcastMessage->message_body,
            ['statusCallback' => $statusCallback]
        );

        $this->broadcastMessage->update([
            'twilio_message_sid' => $result['sid'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Broadcast SMS failed', [
            'broadcast_message_id' => $this->broadcastMessage->id,
            'error' => $e->getMessage(),
        ]);

        $this->broadcastMessage->update(['status' => 'failed']);
    }
}
