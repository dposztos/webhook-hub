<?php

namespace App\Services\Rules;

use App\Models\Message;

/**
 * The data set that conditions and templates are allowed to reference.
 */
class MessageContext
{
    /** @param array<string, mixed> $data */
    private function __construct(public readonly array $data) {}

    public static function fromMessage(Message $message): self
    {
        $endpoint = $message->endpoint;
        $group = $endpoint?->group;

        $headers = [];
        foreach ((array) $message->headers as $name => $value) {
            $headers[strtolower((string) $name)] = $value;
        }

        return new self([
            'json' => is_array($message->body_json) ? $message->body_json : [],
            'body' => (string) $message->body,
            'headers' => $headers,
            'query' => (array) $message->query,
            'meta' => [
                'id' => $message->uuid,
                'method' => $message->method,
                'url' => $message->url,
                'suffix' => (string) $message->path_suffix,
                'ip' => (string) $message->ip,
                'user_agent' => (string) $message->user_agent,
                'content_type' => (string) $message->content_type,
                'size' => (int) $message->size,
                'received_at' => optional($message->created_at)->toDateTimeString(),
                'received_at_local' => optional($message->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                // Deprecated alias kept for templates written before the rename.
                'received_at_hu' => optional($message->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'is_json' => is_array($message->body_json),
            ],
            'endpoint' => [
                'name' => $endpoint?->name,
                'slug' => $endpoint?->slug,
                'uuid' => $endpoint?->uuid,
                'url' => $endpoint?->url(),
            ],
            'group' => [
                'name' => $group?->name,
                'slug' => $group?->slug,
                'path' => $group?->pathLabel(),
            ],
        ]);
    }

    /**
     * Sample context for the hints in the template editor.
     */
    public static function sample(): self
    {
        return new self([
            'json' => ['event' => 'order.paid', 'order' => ['id' => 'ORD-1234', 'total' => 24990]],
            'body' => '{"event":"order.paid"}',
            'headers' => ['content-type' => 'application/json'],
            'query' => [],
            'meta' => [
                'method' => 'POST',
                'received_at' => now()->toDateTimeString(),
                'received_at_local' => now()->format('Y-m-d H:i:s'),
            ],
            'endpoint' => ['name' => 'Orders'],
            'group' => ['name' => 'Customers', 'path' => 'Customers / ACME'],
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
