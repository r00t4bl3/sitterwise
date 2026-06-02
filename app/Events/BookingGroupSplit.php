<?php

namespace App\Events;

use App\Models\BookingGroup;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingGroupSplit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BookingGroup $originalGroup,
        public BookingGroup $newGroup,
        public array $extractedBookingIds,
    ) {}
}
