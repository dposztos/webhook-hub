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
     * Store an incoming HTTP request as a message.
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
     * The structured form of the body where one can be extracted: JSON,
     * form-urlencoded or multipart fields. Rules and templates reach it
     * through the `json.` prefix.
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

            return $parsed ?: $this->postFields($request);
        }

        if (str_starts_with($type, 'multipart/form-data')) {
            return $this->postFields($request);
        }

        // Form fields arriving without a Content-Type — some senders do this
        return $this->postFields($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postFields(Request $request): ?array
    {
        $post = $request->post();

        return is_array($post) && $post ? $post : null;
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
        // Deliberately just $request->ip(). Reading X-Forwarded-For by hand would
        // ignore the trusted-proxy configuration and let any direct client claim
        // whatever address it likes; Laravel only honours the header when the
        // peer is a trusted proxy (see TRUSTED_PROXIES in bootstrap/app.php).
        return $request->ip();
    }
}
