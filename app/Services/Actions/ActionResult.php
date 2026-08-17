<?php

namespace App\Services\Actions;

class ActionResult
{
    /**
     * @param  array<string, mixed>  $detail
     */
    private function __construct(
        public readonly string $status,
        public readonly string $summary,
        public readonly array $detail = [],
        public readonly ?string $error = null,
    ) {}

    /** @param array<string, mixed> $detail */
    public static function success(string $summary, array $detail = []): self
    {
        return new self('success', $summary, $detail);
    }

    /** @param array<string, mixed> $detail */
    public static function skipped(string $summary, array $detail = []): self
    {
        return new self('skipped', $summary, $detail);
    }

    /** @param array<string, mixed> $detail */
    public static function failed(string $error, array $detail = []): self
    {
        return new self('failed', 'Sikertelen', $detail, $error);
    }
}
