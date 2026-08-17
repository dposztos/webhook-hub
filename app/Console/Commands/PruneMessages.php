<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use App\Models\Message;
use Illuminate\Console\Command;

class PruneMessages extends Command
{
    protected $signature = 'webhook:prune {--dry-run : Only count, do not delete}';

    public function __construct()
    {
        // Set before parent::__construct(), which is what copies the value onto
        // the underlying Symfony command.
        $this->description = __('webhookhub.console.prune_description');

        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        $defaultDays = config('webhookhub.default_retention_days');

        Endpoint::query()->chunkById(100, function ($endpoints) use (&$deleted, $dryRun, $defaultDays) {
            foreach ($endpoints as $endpoint) {
                $before = $deleted;
                $days = $endpoint->retention_days ?? $defaultDays;

                if ($days) {
                    $query = Message::where('endpoint_id', $endpoint->id)
                        ->where('created_at', '<', now()->subDays($days));

                    $deleted += $dryRun ? $query->count() : $query->delete();
                }

                if ($endpoint->max_messages) {
                    $keepFrom = Message::where('endpoint_id', $endpoint->id)
                        ->orderByDesc('id')
                        ->skip($endpoint->max_messages - 1)
                        ->take(1)
                        ->value('id');

                    if ($keepFrom) {
                        $query = Message::where('endpoint_id', $endpoint->id)->where('id', '<', $keepFrom);
                        $deleted += $dryRun ? $query->count() : $query->delete();
                    }
                }

                if (! $dryRun && $deleted > $before) {
                    $endpoint->recountMessages();
                }
            }
        });

        $this->info(__(
            $dryRun ? 'webhookhub.console.prune_marked' : 'webhookhub.console.prune_deleted',
            ['count' => $deleted]
        ));

        return self::SUCCESS;
    }
}
