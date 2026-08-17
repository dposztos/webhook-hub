<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use Illuminate\Console\Command;

class RecountMessages extends Command
{
    protected $signature = 'webhook:recount';

    public function __construct()
    {
        // Set before parent::__construct(), which is what copies the value onto
        // the underlying Symfony command.
        $this->description = __('webhookhub.console.recount_description');

        parent::__construct();
    }

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

        $this->info($fixed
            ? __('webhookhub.console.recount_fixed', ['count' => $fixed])
            : __('webhookhub.console.recount_ok'));

        return self::SUCCESS;
    }
}
