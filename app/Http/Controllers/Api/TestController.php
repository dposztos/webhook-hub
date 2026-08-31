<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\RuleAction;
use App\Services\Rules\ConditionEvaluator;
use App\Services\Rules\ConditionValidator;
use App\Services\Rules\MessageContext;
use App\Services\Rules\RuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The endpoints behind the editor's "try it" buttons: testing conditions against
 * a real message, previewing a template, and sending a test e-mail.
 */
class TestController extends Controller
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly ConditionValidator $validator,
        private readonly RuleEngine $engine,
    ) {}

    public function conditions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conditions' => ['required', 'array'],
            'message_uuid' => ['required', 'string'],
        ]);

        $errors = $this->validator->validate($data['conditions']);

        if ($errors) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        $context = $this->context($data['message_uuid']);
        $matched = $this->evaluator->evaluate($data['conditions'], $context);

        return response()->json([
            'ok' => true,
            'matched' => $matched,
            'trace' => $this->evaluator->trace(),
        ]);
    }

    /**
     * Dry-run an action: by default it only renders, nothing leaves the app.
     * Passing `send_to` sends a real test e-mail to that address; passing
     * `run` actually executes a script action.
     */
    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:email,script'],
            'config' => ['required', 'array'],
            'message_uuid' => ['required', 'string'],
            'send_to' => ['nullable', 'email'],
            'run' => ['nullable', 'boolean'],
        ]);

        $context = $this->context($data['message_uuid']);
        $config = $data['config'];

        if (! empty($data['send_to'])) {
            $config['to'] = $data['send_to'];
            $config['cc'] = '';
            $config['bcc'] = '';
        }

        $action = new RuleAction([
            'type' => $data['type'],
            'config' => $config,
        ]);

        $live = $data['type'] === 'script'
            ? $request->boolean('run')
            : ! empty($data['send_to']);

        $timed = $this->engine->runAction($action, $context, dryRun: ! $live);
        $result = $timed->result;

        return response()->json([
            'status' => $result->status,
            'summary' => $result->summary,
            'error' => $result->error,
            // Everything the action reported, minus the rendered mail body,
            // which the preview block below carries separately.
            'detail' => collect($result->detail)->except('html')->all(),
            'preview' => [
                'to' => $result->detail['to'] ?? [],
                'cc' => $result->detail['cc'] ?? [],
                'subject' => $result->detail['subject'] ?? null,
                'html' => $result->detail['html'] ?? null,
            ],
            'duration_ms' => $timed->durationMs,
        ]);
    }

    /**
     * The variables available to templates and conditions for a given message.
     */
    public function context(string $uuid): array
    {
        $message = Message::with('endpoint.group')->where('uuid', $uuid)->firstOrFail();

        return MessageContext::fromMessage($message)->toArray();
    }

    public function variables(string $uuid): JsonResponse
    {
        $context = $this->context($uuid);

        return response()->json([
            'context' => $context,
            'suggestions' => $this->suggestions($context),
        ]);
    }

    /**
     * Clickable variable suggestions derived from the captured message:
     * ready-made references shaped like "json.order.items.0.sku".
     *
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function suggestions(array $context): array
    {
        $out = [];

        $walk = function (mixed $value, string $prefix, int $depth) use (&$walk, &$out): void {
            if ($depth > 6 || count($out) > 300) {
                return;
            }

            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $walk($item, $prefix === '' ? (string) $key : $prefix.'.'.$key, $depth + 1);
                }

                return;
            }

            $out[] = [
                'path' => $prefix,
                'value' => is_bool($value) ? ($value ? 'true' : 'false') : mb_strimwidth((string) $value, 0, 80, '…'),
            ];
        };

        foreach (['json', 'query', 'meta', 'headers', 'endpoint', 'group'] as $source) {
            $walk($context[$source] ?? [], $source, 0);
        }

        return $out;
    }
}
