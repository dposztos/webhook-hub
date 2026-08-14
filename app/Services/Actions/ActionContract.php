<?php

namespace App\Services\Actions;

use App\Models\RuleAction;

interface ActionContract
{
    /**
     * @param array<string, mixed> $context A beérkezett üzenet adatai (MessageContext)
     */
    public function execute(RuleAction $action, array $context, bool $dryRun = false): ActionResult;
}
