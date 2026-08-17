<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessage;
use App\Models\Endpoint;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    /**
     * Message list. Filterable by endpoint or by a whole group (subgroups
     * included), plus free-text search over the body, URL and headers.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', 'string', 'max:10'],
            'only' => ['nullable', 'in:matched,failed,unprocessed,unread'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $query = Message::query()->with('endpoint:id,name,slug,group_id')->orderByDesc('id');

        if ($request->filled('endpoint_id')) {
            $query->where('endpoint_id', (int) $request->input('endpoint_id'));
        } elseif ($request->filled('group_id')) {
            $group = Group::findOrFail((int) $request->input('group_id'));
            $endpointIds = Endpoint::whereIn('group_id', $group->descendantIds())->pluck('id');
            $query->whereIn('endpoint_id', $endpointIds);
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper((string) $request->input('method')));
        }

        if ($request->filled('q')) {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('q')).'%';
            $query->where(function ($q) use ($needle) {
                $q->where('body', 'ilike', $needle)
                    ->orWhere('url', 'ilike', $needle)
                    ->orWhereRaw('headers::text ilike ?', [$needle]);
            });
        }

        match ($request->input('only')) {
            'matched' => $query->whereRaw('jsonb_array_length(matched_rules) > 0'),
            'failed' => $query->where('actions_failed', '>', 0),
            'unprocessed' => $query->whereNull('processed_at'),
            'unread' => $query->whereNull('read_at'),
            default => null,
        };

        $page = $query->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (Message $m) => [
                'uuid' => $m->uuid,
                'endpoint' => ['id' => $m->endpoint_id, 'name' => $m->endpoint?->name],
                'method' => $m->method,
                'content_type' => $m->content_type,
                'size' => $m->size,
                'ip' => $m->ip,
                'created_at' => $m->created_at?->toIso8601String(),
                'read' => $m->read_at !== null,
                'preview' => $m->preview(),
                'matched_rules' => $m->matched_rules,
                'actions_ok' => $m->actions_ok,
                'actions_failed' => $m->actions_failed,
                'processed' => $m->processed_at !== null,
            ]),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $message = Message::with(['endpoint.group', 'actionRuns.rule:id,name'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Opening a message marks it read.
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'uuid' => $message->uuid,
            'endpoint' => [
                'id' => $message->endpoint_id,
                'name' => $message->endpoint?->name,
                'url' => $message->endpoint?->url(),
                'group_path' => $message->endpoint?->group?->pathLabel(),
            ],
            'method' => $message->method,
            'url' => $message->url,
            'path_suffix' => $message->path_suffix,
            'query' => $message->query,
            'headers' => $message->headers,
            'body' => $message->body,
            'body_json' => $message->body_json,
            'files' => $message->files,
            'content_type' => $message->content_type,
            'size' => $message->size,
            'truncated' => $message->truncated,
            'ip' => $message->ip,
            'user_agent' => $message->user_agent,
            'response_status' => $message->response_status,
            'created_at' => $message->created_at?->toIso8601String(),
            'processed_at' => $message->processed_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'matched_rules' => $message->matched_rules,
            'runs' => $message->actionRuns->map(fn ($run) => [
                'id' => $run->id,
                'rule' => $run->rule?->name,
                'rule_id' => $run->rule_id,
                'type' => $run->type,
                'status' => $run->status,
                'summary' => $run->summary,
                'error' => $run->error,
                'detail' => $run->detail,
                'duration_ms' => $run->duration_ms,
                'created_at' => $run->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function raw(string $uuid): Response
    {
        $message = Message::where('uuid', $uuid)->firstOrFail();

        return new Response($message->body ?? '', 200, [
            'Content-Type' => $message->content_type ?: 'text/plain; charset=utf-8',
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $message = Message::where('uuid', $uuid)->firstOrFail();
        $endpoint = $message->endpoint;

        $message->delete();
        $endpoint?->recountMessages();

        return response()->json([
            'deleted' => true,
            'messages_count' => $endpoint?->messages_count,
        ]);
    }

    /**
     * Delete every message of an endpoint.
     */
    public function clear(Endpoint $endpoint): JsonResponse
    {
        $deleted = Message::where('endpoint_id', $endpoint->id)->delete();
        $endpoint->recountMessages();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Mark unread again, to put it back on the "look at this" pile.
     */
    public function markUnread(string $uuid): JsonResponse
    {
        $message = Message::where('uuid', $uuid)->firstOrFail();
        $message->forceFill(['read_at' => null])->save();

        return response()->json(['read' => false]);
    }

    /**
     * Mark every message of an endpoint (or a whole group) as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint_id' => ['nullable', 'integer', 'exists:endpoints,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $query = Message::query()->whereNull('read_at');

        if ($request->filled('endpoint_id')) {
            $query->where('endpoint_id', (int) $request->input('endpoint_id'));
        } elseif ($request->filled('group_id')) {
            $group = Group::findOrFail((int) $request->input('group_id'));
            $query->whereIn('endpoint_id', Endpoint::whereIn('group_id', $group->descendantIds())->pluck('id'));
        }

        return response()->json(['marked' => $query->update(['read_at' => now()])]);
    }

    /**
     * Re-run the rules against a stored message — for real, actions included.
     */
    public function replay(string $uuid): JsonResponse
    {
        $message = Message::where('uuid', $uuid)->firstOrFail();
        $message->update(['processed_at' => null, 'matched_rules' => [], 'actions_ok' => 0, 'actions_failed' => 0]);

        ProcessMessage::dispatch($message->id);

        return response()->json(['queued' => true]);
    }
}
