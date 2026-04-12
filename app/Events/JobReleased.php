<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $bookingId;

    public int $caregiverId;

    public function __construct(int $bookingId, int $caregiverId)
    {
        $this->bookingId = $bookingId;
        $this->caregiverId = $caregiverId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('booking.'.$this->bookingId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'caregiver_id' => $this->caregiverId,
        ];
    }
}
