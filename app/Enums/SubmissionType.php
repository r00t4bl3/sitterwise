<?php
namespace App\Enums;

enum SubmissionType: string {
    case Guest    = 'guest';
    case LoggedIn = 'logged_in';
    case Admin    = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Guest    => 'Guest',
            self::LoggedIn => 'Logged In',
            self::Admin    => 'Admin',
        };
    }
}