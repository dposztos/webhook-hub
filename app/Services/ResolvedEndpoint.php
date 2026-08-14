<?php

namespace App\Services;

use App\Models\Endpoint;

class ResolvedEndpoint
{
    public function __construct(
        public readonly Endpoint $endpoint,
        public readonly string $suffix = '',
    ) {}

    /**
     * A "/404" alakú lezáró szegmens felülírja a válasz státuszkódját
     * (a webhook.site eredeti viselkedése).
     */
    public function statusOverride(): ?int
    {
        $parts = array_filter(explode('/', $this->suffix));
        $last = end($parts);

        if ($last !== false && preg_match('/^[1-5][0-9]{2}$/', $last)) {
            return (int) $last;
        }

        return null;
    }
}
