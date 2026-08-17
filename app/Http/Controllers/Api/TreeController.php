<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rearranging the tree: moving a group or endpoint under another parent, and
 * reordering it among its siblings.
 */
class TreeController extends Controller
{
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:group,endpoint'],
            'id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:groups,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        $node = $data['type'] === 'group'
            ? Group::findOrFail($data['id'])
            : Endpoint::findOrFail($data['id']);

        if ($node instanceof Group) {
            $this->guardAgainstCycle($node, $parentId);
        }

        $result = DB::transaction(function () use ($node, $parentId, $data) {
            $originalSlug = $node->slug;

            $node->slug = $this->availableSlug($node, $parentId);
            $node instanceof Group
                ? $node->parent_id = $parentId
                : $node->group_id = $parentId;
            $node->save();

            $this->reorder($node, $parentId, $data['position'] ?? null);

            return [
                'slug_changed' => $originalSlug !== $node->slug,
                'slug' => $node->slug,
            ];
        });

        return response()->json($result + ['moved' => true]);
    }

    /**
     * A free slug under the target parent; on a clash, a numbered variant.
     * (The slug is part of the URL, so it is only touched when unavoidable.)
     */
    private function availableSlug(Model $node, ?int $parentId): string
    {
        $base = $node->slug;
        $slug = $base;
        $i = 2;

        while ($this->slugTaken($node, $parentId, $slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function slugTaken(Model $node, ?int $parentId, string $slug): bool
    {
        $query = $node instanceof Group
            ? Group::where('parent_id', $parentId)
            : Endpoint::where('group_id', $parentId);

        return $query->where('slug', $slug)->whereKeyNot($node->getKey())->exists();
    }

    /**
     * Renumber the siblings so the moved node lands at the requested position.
     */
    private function reorder(Model $node, ?int $parentId, ?int $position): void
    {
        $siblings = ($node instanceof Group
            ? Group::where('parent_id', $parentId)
            : Endpoint::where('group_id', $parentId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $ordered = $siblings->reject(fn (Model $sibling) => $sibling->getKey() === $node->getKey())->values();

        $index = $position === null
            ? $ordered->count()
            : max(0, min($position, $ordered->count()));

        $ordered->splice($index, 0, [$node]);

        foreach ($ordered as $i => $sibling) {
            if ($sibling->position !== $i) {
                $sibling->position = $i;
                $sibling->save();
            }
        }
    }

    private function guardAgainstCycle(Group $group, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $group->id || in_array($parentId, $group->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => __('webhookhub.validation.group_cycle'),
            ]);
        }
    }
}
