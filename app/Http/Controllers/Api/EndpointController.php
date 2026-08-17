<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EndpointController extends Controller
{
    public function show(Endpoint $endpoint): JsonResponse
    {
        return response()->json($this->payload($endpoint));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'response_status' => ['nullable', 'integer', 'between:100,599'],
            'response_body' => ['nullable', 'string', 'max:65535'],
            'response_content_type' => ['nullable', 'string', 'max:120'],
            'response_delay_ms' => ['nullable', 'integer', 'between:0,10000'],
            'cors' => ['nullable', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'between:1,3650'],
            'max_messages' => ['nullable', 'integer', 'between:1,10000000'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name'], $data['group_id'] ?? null);

        $endpoint = Endpoint::create($data);

        return response()->json($this->payload($endpoint), 201);
    }

    public function update(Request $request, Endpoint $endpoint): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
            'response_status' => ['sometimes', 'integer', 'between:100,599'],
            'response_body' => ['nullable', 'string', 'max:65535'],
            'response_content_type' => ['sometimes', 'string', 'max:120'],
            'response_delay_ms' => ['sometimes', 'integer', 'between:0,10000'],
            'cors' => ['sometimes', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'between:1,3650'],
            'max_messages' => ['nullable', 'integer', 'between:1,10000000'],
        ]);

        $endpoint->fill($data)->save();

        return response()->json($this->payload($endpoint->fresh()));
    }

    public function destroy(Endpoint $endpoint): JsonResponse
    {
        $endpoint->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Rotate the secret; the old URL stops working immediately.
     */
    public function rotateSecret(Endpoint $endpoint): JsonResponse
    {
        $endpoint->secret = Endpoint::newSecret();
        $endpoint->save();

        return response()->json($this->payload($endpoint));
    }

    /** @return array<string, mixed> */
    private function payload(Endpoint $endpoint): array
    {
        $endpoint->loadMissing('group');

        return array_merge($endpoint->toArray(), [
            'url' => $endpoint->url(),
            'group_path' => $endpoint->group?->pathLabel(),
        ]);
    }

    private function uniqueSlug(string $name, ?int $groupId): string
    {
        $base = Str::slug($name) ?: 'endpoint';
        $slug = $base;
        $i = 2;

        while (Endpoint::where('group_id', $groupId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
