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
use Illuminate\Support\Str;
use Throwable;

class RuleEngine
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly ActionRegistry $registry,
    ) {}

    /**
     * Rules that apply to an endpoint: its own, those of every group above it up
     * to the root, and the global ones. Ordered by ascending priority.
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
     * Runs the message through the rules: evaluation plus action execution.
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

            // Actions of one rule form a chain: each one's result is added to the
            // context under "steps", so a later action's templates can use what an
            // earlier one produced. It starts empty for every rule, so a rule never
            // depends on which rules happened to run before it.
            $actionContext = $context;
            $actionContext['steps'] = [];
            $previous = null;

            foreach ($rule->actions as $position => $action) {
                if (! $action->enabled) {
                    continue;
                }

                if ($budget-- <= 0) {
                    $this->log($message, $rule, $action, ActionResult::skipped(
                        __('webhookhub.actions.limit_reached', [
                            'limit' => config('webhookhub.max_actions_per_message'),
                        ])
                    ), 0);

                    break 2;
                }

                if (($action->config['only_if_previous_succeeded'] ?? false) && $previous !== 'success') {
                    // Without this an e-mail built from a query that failed would
                    // still go out, carrying nothing.
                    $result = ActionResult::skipped(__('webhookhub.actions.previous_failed', [
                        // The status is a code ("failed"); the sentence around it
                        // is translated, so the word inside it has to be too.
                        'status' => $previous !== null
                            ? __('webhookhub.actions.status.'.$previous)
                            : __('webhookhub.actions.no_previous'),
                    ]));

                    $actionContext['steps'][$this->stepKey($action, $position, $actionContext['steps'])] = $this->stepValue($result);
                    $previous = $result->status;

                    $this->log($message, $rule, $action, $result, 0);

                    continue;
                }

                $timed = $this->runAction($action, $actionContext);

                match ($timed->status()) {
                    'success' => $ok++,
                    'failed' => $failed++,
                    default => null,
                };

                $actionContext['steps'][$this->stepKey($action, $position, $actionContext['steps'])] = $this->stepValue($timed->result);
                $previous = $timed->status();

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
     * @param  array<string, mixed>  $context
     */
    public function matches(Rule $rule, array $context): bool
    {
        $conditions = is_array($rule->conditions) ? $rule->conditions : [];

        try {
            return $this->evaluator->evaluate($conditions, $context);
        } catch (Throwable $e) {
            Log::warning('Rule evaluation crashed', ['rule' => $rule->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
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

    /**
     * The name a step is addressed by in a template: the action's own name,
     * lowercased and underscored, or "step_1" when it has none. A repeated name
     * gets a numeric suffix rather than quietly overwriting the earlier step.
     *
     * @param  array<string, mixed>  $steps
     */
    private function stepKey(RuleAction $action, int $position, array $steps): string
    {
        $name = Str::slug((string) ($action->name ?? ''), '_');
        $key = $name !== '' ? $name : 'step_'.($position + 1);

        if (! array_key_exists($key, $steps)) {
            return $key;
        }

        $suffix = 2;

        while (array_key_exists($key.'_'.$suffix, $steps)) {
            $suffix++;
        }

        return $key.'_'.$suffix;
    }

    /**
     * What a later step sees of an earlier one. "output" is the parsed JSON a
     * script printed; everything else is there so a template can tell what
     * happened without having to parse a summary sentence.
     *
     * @return array<string, mixed>
     */
    private function stepValue(ActionResult $result): array
    {
        return [
            'status' => $result->status,
            'summary' => $result->summary,
            'error' => $result->error,
            'output' => $result->detail['output_json'] ?? null,
            'stdout' => $result->detail['stdout'] ?? null,
            'exit_code' => $result->detail['exit_code'] ?? null,
        ];
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
            // The rendered HTML stays out of the log; only the essentials go in.
            'detail' => collect($result->detail)->except('html')->all(),
            'duration_ms' => $durationMs,
        ]);
    }
}
