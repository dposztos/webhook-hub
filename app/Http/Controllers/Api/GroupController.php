<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    /**
     * A teljes fa: csoportok (tetszőleges mélységig) és bennük az endpointok.
     */
    public function tree(): JsonResponse
    {
        $groups = Group::query()->orderBy('position')->orderBy('name')->get();
        $endpoints = Endpoint::query()->orderBy('position')->orderBy('name')->get();

        $ruleCounts = DB::table('rules')
            ->selectRaw('group_id, endpoint_id, count(*) as total')
            ->where('enabled', true)
            ->groupBy('group_id', 'endpoint_id')
            ->get();

        $rulesByGroup = $ruleCounts->whereNotNull('group_id')->pluck('total', 'group_id');
        $rulesByEndpoint = $ruleCounts->whereNotNull('endpoint_id')->pluck('total', 'endpoint_id');

        $endpointsByGroup = $endpoints->groupBy(fn (Endpoint $e) => $e->group_id ?? 0);

        // Az olvasatlanok száma nem denormalizált érték: egy lekérdezésből jön.
        $unread = DB::table('messages')
            ->selectRaw('endpoint_id, count(*) as total')
            ->whereNull('read_at')
            ->groupBy('endpoint_id')
            ->pluck('total', 'endpoint_id');

        $build = function (?int $parentId) use (&$build, $groups, $endpointsByGroup, $rulesByGroup, $rulesByEndpoint, $unread): array {
            return $groups
                ->where('parent_id', $parentId)
                ->map(function (Group $group) use ($build, $endpointsByGroup, $rulesByGroup, $rulesByEndpoint, $unread) {
                    $children = $build($group->id);
                    $groupEndpoints = $this->endpointPayload(
                        $endpointsByGroup->get($group->id, collect()),
                        $rulesByEndpoint,
                        $unread
                    );

                    return [
                        'id' => $group->id,
                        'type' => 'group',
                        'name' => $group->name,
                        'slug' => $group->slug,
                        'description' => $group->description,
                        'color' => $group->color,
                        'rules_count' => (int) ($rulesByGroup[$group->id] ?? 0),
                        'children' => $children,
                        'endpoints' => $groupEndpoints,
                        // A csoportnál az alatta lévő összes olvasatlan összege látszik.
                        'unread_count' => collect($children)->sum('unread_count')
                            + collect($groupEndpoints)->sum('unread_count'),
                    ];
                })
                ->values()
                ->all();
        };

        return response()->json([
            'groups' => $build(null),
            'endpoints' => $this->endpointPayload($endpointsByGroup->get(0, collect()), $rulesByEndpoint, $unread),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:groups,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name'], $data['parent_id'] ?? null);

        return response()->json(Group::create($data), 201);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
            'position' => ['sometimes', 'integer'],
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ]);

        if (array_key_exists('parent_id', $data)) {
            $this->guardAgainstCycle($group, $data['parent_id']);
        }

        // A slug az URL része: átnevezéskor nem változtatjuk magától,
        // mert azzal minden addigi webhook-cím elromlana.
        $group->fill($data)->save();

        return response()->json($group);
    }

    public function destroy(Group $group): JsonResponse
    {
        // A kaszkád törli az alcsoportokat, endpointokat és üzeneteket is.
        $group->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Endpoint> $endpoints
     * @param \Illuminate\Support\Collection<int|string, int> $rulesByEndpoint
     * @return array<int, array<string, mixed>>
     */
    private function endpointPayload($endpoints, $rulesByEndpoint, $unread = null): array
    {
        return $endpoints->map(fn (Endpoint $endpoint) => [
            'unread_count' => (int) ($unread[$endpoint->id] ?? 0),
            'id' => $endpoint->id,
            'type' => 'endpoint',
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'slug' => $endpoint->slug,
            'enabled' => $endpoint->enabled,
            'url' => $endpoint->url(),
            'messages_count' => $endpoint->messages_count,
            'last_message_at' => $endpoint->last_message_at?->toIso8601String(),
            'rules_count' => (int) ($rulesByEndpoint[$endpoint->id] ?? 0),
        ])->values()->all();
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug($name) ?: 'csoport';
        $slug = $base;
        $i = 2;

        while (Group::where('parent_id', $parentId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function guardAgainstCycle(Group $group, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $group->id || in_array($parentId, $group->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A csoport nem kerülhet önmaga (vagy saját leszármazottja) alá.',
            ]);
        }
    }
}
