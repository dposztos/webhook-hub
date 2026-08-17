<?php

namespace App\Services\Rules;

use App\Models\ActionRun;
use App\Models\Endpoint;
use App\Models\Message;
use App\Models\Rule;
use App\Models\RuleAction;
use App\Services\Actions\ActionRegistry;
use App\Services\Actions\ActionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RuleEngine
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly ActionRegistry $registry,
    ) {}

    /**
     * Egy endpointra érvényes szabályok: a sajátjai, a fölötte lévő csoportoké
     * (a gyökérig), és a globálisak. Prioritás szerint növekvő sorrendben.
     *
     * @return Collection<int, Rule>
     */
    public function rulesFor(Endpoint $endpoint): Collection
    {
        $groupIds = $endpoint->groupChainIds();

        return Rule::query()
            ->with('actions')
            ->where('enabled', true)
            ->where(function ($query) use ($endpoint, $groupIds) {
                $query->where('endpoint_id', $endpoint->id);

                if ($groupIds) {
                    $query->orWhereIn('group_id', $groupIds);
                }

                $query->orWhere(fn ($q) => $q->whereNull('endpoint_id')->whereNull('group_id'));
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Az üzenet átfuttatása a szabályokon: kiértékelés + akciók végrehajtása.
     */
    public function process(Message $message): void
    {
        $endpoint = $message->endpoint;

        if (! $endpoint) {
            return;
        }

        $context = MessageContext::fromMessage($message)->toArray();
        $rules = $this->rulesFor($endpoint);

        $matched = [];
        $ok = 0;
        $failed = 0;
        $budget = (int) config('webhookhub.max_actions_per_message');

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $context)) {
                continue;
            }

            $matched[] = ['id' => $rule->id, 'name' => $rule->name];

            DB::table('rules')->where('id', $rule->id)->update([
                'match_count' => DB::raw('match_count + 1'),
                'last_matched_at' => now(),
            ]);

            foreach ($rule->actions as $action) {
                if (! $action->enabled) {
                    continue;
                }

                if ($budget-- <= 0) {
                    $this->log($message, $rule, $action, ActionResult::skipped(
                        'Akció-korlát elérve ('.config('webhookhub.max_actions_per_message').' akció/üzenet)'
                    ), 0);

                    break 2;
                }

                $timed = $this->runAction($action, $context);

                match ($timed->status()) {
                    'success' => $ok++,
                    'failed' => $failed++,
                    default => null,
                };

                $this->log($message, $rule, $action, $timed->result, $timed->durationMs);
            }

            if ($rule->stop_processing) {
                break;
            }
        }

        $message->forceFill([
            'processed_at' => now(),
            'matched_rules' => $matched,
            'actions_ok' => $ok,
            'actions_failed' => $failed,
        ])->save();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function matches(Rule $rule, array $context): bool
    {
        $conditions = is_array($rule->conditions) ? $rule->conditions : [];

        try {
            return $this->evaluator->evaluate($conditions, $context);
        } catch (Throwable $e) {
            Log::warning('Szabály kiértékelése elszállt', ['rule' => $rule->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function runAction(RuleAction $action, array $context, bool $dryRun = false): TimedActionResult
    {
        $started = microtime(true);

        try {
            $result = $this->registry->make($action->type)->execute($action, $context, $dryRun);
        } catch (Throwable $e) {
            $result = ActionResult::failed(get_class($e).': '.$e->getMessage());
        }

        return new TimedActionResult($result, (int) round((microtime(true) - $started) * 1000));
    }

    private function log(Message $message, Rule $rule, RuleAction $action, ActionResult $result, int $durationMs): void
    {
        ActionRun::create([
            'message_id' => $message->id,
            'rule_id' => $rule->id,
            'rule_action_id' => $action->id,
            'type' => $action->type,
            'status' => $result->status,
            'summary' => $result->summary,
            'error' => $result->error,
            // A kirenderelt HTML-t nem tesszük a naplóba, csak a lényeget.
            'detail' => collect($result->detail)->except('html')->all(),
            'duration_ms' => $durationMs,
        ]);
    }
}
