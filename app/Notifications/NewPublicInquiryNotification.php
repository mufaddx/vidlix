<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewPublicInquiryNotification extends Notification
{
    use Queueable;

    public function __construct(public Conversation $conversation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_inquiry',
            'conversation_uuid' => $this->conversation->conversation_uuid,
            'subject' => $this->conversation->subject,
        ];
    }
}
