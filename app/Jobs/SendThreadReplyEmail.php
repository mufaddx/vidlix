<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Email\OutboundEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendThreadReplyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(OutboundEmailService $outbound): void
    {
        $message = Message::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        $outbound->send($message);
    }
}
