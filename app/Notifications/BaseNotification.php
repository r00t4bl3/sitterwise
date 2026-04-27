<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    public function via(object $notifiable): array
    {
        if (config('app.env') !== 'production') {
            return ['database'];
        }

        return $this->channels($notifiable);
    }

    abstract protected function channels(object $notifiable): array;
}
