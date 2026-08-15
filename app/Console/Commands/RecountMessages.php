<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use Illuminate\Console\Command;

class RecountMessages extends Command
{
    protected $signature = 'webhook:recount';

    protected $description = 'Az endpointok üzenetszámlálóinak újraszámolása a tárolt üzenetekből';

    public function handle(): int
    {
        $fixed = 0;

        Endpoint::query()->chunkById(100, function ($endpoints) use (&$fixed) {
            foreach ($endpoints as $endpoint) {
                $before = $endpoint->messages_count;
                $endpoint->recountMessages();

                if ($before !== $endpoint->messages_count) {
                    $this->line("  {$endpoint->name}: {$before} → {$endpoint->messages_count}");
                    $fixed++;
                }
            }
        });

        $this->info($fixed ? "Javítva: {$fixed} endpoint számlálója" : 'Minden számláló pontos volt.');

        return self::SUCCESS;
    }
}
