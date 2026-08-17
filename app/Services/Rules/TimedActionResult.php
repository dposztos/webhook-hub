<?php

namespace App\Services\Rules;

use App\Services\Actions\ActionResult;

class TimedActionResult
{
    public function __construct(
        public readonly ActionResult $result,
        public readonly int $durationMs,
    ) {}

    public function status(): string
    {
        return $this->result->status;
    }
}
