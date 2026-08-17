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
     * A trailing segment such as "/404" overrides the response status code
     * (the original webhook.site behaviour).
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
