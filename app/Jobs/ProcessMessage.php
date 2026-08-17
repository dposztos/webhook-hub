<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Rules\RuleEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $messageId) {}

    public function handle(RuleEngine $engine): void
    {
        $message = Message::with('endpoint.group')->find($this->messageId);

        if (! $message || $message->processed_at) {
            return;
        }

        $engine->process($message);
    }
}
