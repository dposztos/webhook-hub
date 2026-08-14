<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageRecorder
{
    /**
     * Beérkező HTTP-kérés eltárolása üzenetként.
     */
    public function record(Endpoint $endpoint, Request $request, string $suffix = ''): Message
    {
        $maxBody = (int) config('webhookhub.max_body_bytes');
        $raw = $request->getContent();
        $size = strlen($raw);
        $truncated = false;

        if ($size > $maxBody) {
            $raw = substr($raw, 0, $maxBody);
            $truncated = true;
        }

        $contentType = (string) $request->header('Content-Type', '');

        $message = new Message([
            'uuid' => (string) Str::uuid(),
            'endpoint_id' => $endpoint->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path_suffix' => $suffix !== '' ? $suffix : null,
            'query' => $request->query(),
            'headers' => $this->headers($request),
            'body' => $raw,
            'body_json' => $this->structuredBody($request, $raw, $contentType),
            'files' => $this->files($request),
            'content_type' => $contentType !== '' ? $contentType : null,
            'size' => $size,
            'truncated' => $truncated,
            'ip' => $this->clientIp($request),
            'user_agent' => $request->userAgent(),
        ]);

        $message->save();

        DB::table('endpoints')->where('id', $endpoint->id)->update([
            'messages_count' => DB::raw('messages_count + 1'),
            'last_message_at' => now(),
        ]);

        return $message;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $out = [];

        foreach ($request->headers->all() as $name => $values) {
            $out[$name] = implode(', ', array_map(fn ($v) => (string) $v, $values));
        }

        ksort($out);

        return $out;
    }

    /**
     * A test strukturált formája, ha kinyerhető: JSON, form-urlencoded vagy multipart mezők.
     * Erre hivatkoznak a szabályok és a sablonok `json.` előtaggal.
     *
     * @return array<string, mixed>|null
     */
    private function structuredBody(Request $request, string $raw, string $contentType): ?array
    {
        $type = strtolower(trim(explode(';', $contentType)[0] ?? ''));

        if (str_contains($type, 'json') || (($raw !== '') && str_starts_with(ltrim($raw), '{')) || str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if ($type === 'application/x-www-form-urlencoded') {
            $parsed = [];
            parse_str($raw, $parsed);

            return $parsed ?: null;
        }

        if (str_starts_with($type, 'multipart/form-data')) {
            $post = $request->post();

            return is_array($post) && $post ? $post : null;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function files(Request $request): array
    {
        $out = [];

        foreach ($request->allFiles() as $field => $files) {
            foreach (is_array($files) ? $files : [$files] as $file) {
                if (! $file) {
                    continue;
                }

                $out[] = [
                    'field' => $field,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        return $out;
    }

    private function clientIp(Request $request): ?string
    {
        // Reverse proxy (nginx-proxy-manager / Cloudflare) mögött a valódi IP a fejlécben van.
        $forwarded = $request->header('CF-Connecting-IP')
            ?? $request->header('X-Forwarded-For');

        if ($forwarded) {
            $first = trim(explode(',', $forwarded)[0]);

            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $request->ip();
    }
}
