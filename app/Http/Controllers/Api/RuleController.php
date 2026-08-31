<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\Rules\ConditionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RuleController extends Controller
{
    public function __construct(private readonly ConditionValidator $validator) {}

    public function index(Request $request): JsonResponse
    {
        $query = Rule::query()->with(['actions', 'group:id,name', 'endpoint:id,name'])
            ->orderBy('priority')->orderBy('id');

        if ($request->filled('endpoint_id')) {
            $query->where('endpoint_id', (int) $request->input('endpoint_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->input('group_id'));
        }

        if ($request->boolean('global')) {
            $query->whereNull('group_id')->whereNull('endpoint_id');
        }

        return response()->json($query->get()->map(fn (Rule $rule) => $this->payload($rule)));
    }

    public function show(Rule $rule): JsonResponse
    {
        return response()->json($this->payload($rule->load(['actions', 'group:id,name', 'endpoint:id,name'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $rule = DB::transaction(function () use ($data) {
            $rule = Rule::create($this->ruleAttributes($data));
            $this->syncActions($rule, $data['actions'] ?? []);

            return $rule;
        });

        return response()->json($this->payload($rule->fresh(['actions'])), 201);
    }

    public function update(Request $request, Rule $rule): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($rule, $data) {
            $rule->fill($this->ruleAttributes($data))->save();

            if (array_key_exists('actions', $data)) {
                $this->syncActions($rule, $data['actions']);
            }
        });

        return response()->json($this->payload($rule->fresh(['actions', 'group:id,name', 'endpoint:id,name'])));
    }

    public function destroy(Rule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'between:1,9999'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'endpoint_id' => ['nullable', 'integer', 'exists:endpoints,id'],
            'conditions' => ['required', 'array'],
            'stop_processing' => ['nullable', 'boolean'],
            'actions' => ['sometimes', 'array', 'max:20'],
            'actions.*.id' => ['nullable', 'integer'],
            'actions.*.type' => ['required', 'string', 'in:email,script'],
            'actions.*.name' => ['nullable', 'string', 'max:150'],
            'actions.*.enabled' => ['nullable', 'boolean'],
            'actions.*.config' => ['required', 'array'],
        ]);

        if (! empty($data['group_id']) && ! empty($data['endpoint_id'])) {
            throw ValidationException::withMessages([
                'endpoint_id' => __('webhookhub.validation.rule_scope'),
            ]);
        }

        $errors = $this->validator->validate($data['conditions']);

        if ($errors) {
            throw ValidationException::withMessages(['conditions' => $errors]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ruleAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'enabled' => $data['enabled'] ?? true,
            'priority' => $data['priority'] ?? 100,
            'group_id' => $data['group_id'] ?? null,
            'endpoint_id' => $data['endpoint_id'] ?? null,
            'conditions' => $data['conditions'],
            'stop_processing' => $data['stop_processing'] ?? false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function syncActions(Rule $rule, array $actions): void
    {
        $keep = [];

        foreach (array_values($actions) as $position => $action) {
            $attributes = [
                'type' => $action['type'],
                'name' => $action['name'] ?? null,
                'enabled' => $action['enabled'] ?? true,
                'position' => $position,
                'config' => $action['config'],
            ];

            $model = ! empty($action['id'])
                ? $rule->actions()->whereKey($action['id'])->first()
                : null;

            if ($model) {
                $model->fill($attributes)->save();
            } else {
                $model = $rule->actions()->create($attributes);
            }

            $keep[] = $model->id;
        }

        $rule->actions()->whereNotIn('id', $keep ?: [0])->delete();
    }

    /** @return array<string, mixed> */
    private function payload(Rule $rule): array
    {
        return array_merge($rule->toArray(), [
            'scope' => $rule->scopeType(),
            'scope_label' => $rule->endpoint?->name ?? $rule->group?->name ?? 'Minden endpoint',
        ]);
    }
}
