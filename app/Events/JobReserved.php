<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobReserved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $bookingId;

    public int $caregiverId;

    public int $expiresIn;

    public function __construct(int $bookingId, int $caregiverId, int $expiresIn = 60)
    {
        $this->bookingId = $bookingId;
        $this->caregiverId = $caregiverId;
        $this->expiresIn = $expiresIn;
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
            'expires_in' => $this->expiresIn,
        ];
    }
}
