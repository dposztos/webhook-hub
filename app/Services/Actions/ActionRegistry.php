<?php

namespace App\Services\Actions;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ActionRegistry
{
    /** @var array<string, class-string<ActionContract>> */
    private const TYPES = [
        'email' => EmailAction::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function make(string $type): ActionContract
    {
        $class = self::TYPES[$type] ?? null;

        if (! $class) {
            throw new InvalidArgumentException(__('webhookhub.actions.unknown_type', ['type' => $type]));
        }

        return $this->container->make($class);
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys(self::TYPES);
    }
}
