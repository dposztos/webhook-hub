<?php

namespace App\Services\Rules;

/**
 * Resolves a field reference against the context: a source plus a dotted path.
 *
 *   json    → the captured JSON body:      "customer.email", "items.0.sku", "items.*.sku"
 *   header  → HTTP header (case-insensitive): "x-signature"
 *   query   → URL query parameter
 *   meta    → method, ip, url, size, received_at, content_type …
 *   body    → the raw body as text (the path is ignored in this case)
 */
class ValueResolver
{
    public const SOURCES = ['json', 'header', 'query', 'meta', 'body'];

    private const MISSING = "\0__missing__\0";

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(array $context, string $source, string $path): mixed
    {
        $value = $this->raw($context, $source, $path);

        return $value === self::MISSING ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function exists(array $context, string $source, string $path): bool
    {
        return $this->raw($context, $source, $path) !== self::MISSING;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function raw(array $context, string $source, string $path): mixed
    {
        $path = trim($path);

        if ($source === 'body') {
            return $context['body'] ?? self::MISSING;
        }

        if ($source === 'header') {
            $headers = $context['headers'] ?? [];
            $key = strtolower($path);

            return array_key_exists($key, $headers) ? $headers[$key] : self::MISSING;
        }

        $root = $context[$source] ?? null;

        if (! is_array($root)) {
            return self::MISSING;
        }

        if ($path === '') {
            return $root;
        }

        return data_get($root, $path, self::MISSING);
    }
}
