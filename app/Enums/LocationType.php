<?php
namespace App\Enums;

enum LocationType: string {
    case Hotel          = 'hotel';
    case PrivateHome    = 'private_home';
    case VacationRental = 'vacation_rental';
    case EventVenue     = 'event_venue';

    public function label(): string
    {
        return match ($this) {
            self::Hotel          => 'Hotel',
            self::PrivateHome    => 'Private Home',
            self::VacationRental => 'Vacation Rental',
            self::EventVenue     => 'Event Venue',
        };
    }
}