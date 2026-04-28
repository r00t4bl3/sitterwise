<?php

namespace App\Enums;

enum DiscoverySource: string
{
    case Concierge = 'concierge';
    case FriendFamily = 'friend_family';
    case Google = 'google';
    case ReturningClient = 'returning_client';
    case CareCom = 'care_com';
    case ArtificialIntelligence = 'AI - ChatGPT, Claude, Grok, Gemini, etc.';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Concierge => 'Concierge',
            self::FriendFamily => 'Friend/Family',
            self::Google => 'Google',
            self::ReturningClient => 'Returning Client',
            self::CareCom => 'Care.com',
            self::ArtificialIntelligence => 'AI - ChatGPT, Claude, Grok, Gemini, etc.',
            self::Other => 'Other',
        };
    }
}
