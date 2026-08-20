<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event', 'push', 'email'];

    /** The events a member can choose to hear about. */
    public const EVENTS = [
        'message_received' => 'A new message in your inbox',
        'project_updated' => 'A project you are on moves stage',
        'payment_confirmed' => 'A payment is confirmed by the provider',
        'withdrawal_updated' => 'A withdrawal changes state',
        'application_decided' => 'A campaign application is accepted or declined',
        'verification_decided' => 'Your verification is approved or rejected',
    ];

    protected function casts(): array
    {
        return ['push' => 'boolean', 'email' => 'boolean'];
    }
}
