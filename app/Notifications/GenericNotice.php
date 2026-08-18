<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenericNotice extends Notification
{
    use Queueable;

    public function __construct(public string $kind, public array $payload = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->kind] + $this->payload;
    }
}
