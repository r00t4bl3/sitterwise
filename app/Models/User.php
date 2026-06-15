<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'role', 'profile_photo_path', 'profile_photo_url', 'last_login_at', 'bubble_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function caregiver(): HasOne
    {
        return $this->hasOne(Caregiver::class);
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isCaregiver(): bool
    {
        return $this->role === 'caregiver';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'caregiver' => 'Caregiver',
            'client' => 'Client',
            default => 'Unknown',
        };
    }

    /**
     * Route notifications for the SMS channel.
     */
    public function routeNotificationForMail(): string
    {
        if ($this->isAdmin() || $this->isSuperAdmin()) {
            return config('mail.from.address');
        }

        return $this->email;
    }

    public function routeNotificationForSms(): ?string
    {
        if ($this->isClient()) {
            return $this->client && ! $this->client->sms_opted_out ? $this->client->phone : null;
        }

        if ($this->isCaregiver()) {
            return $this->caregiver?->phone;
        }

        return null;
    }
}
