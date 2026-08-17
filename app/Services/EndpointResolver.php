<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\Group;

/**
 * Splits the incoming URL path into group chain / endpoint / secret / remainder.
 *
 * Example: "customers/acme/orders/k7f3q9x2mnpq/extra/path"
 *   → endpoint "orders" in group "customers/acme", suffix "extra/path"
 */
class EndpointResolver
{
    public function resolve(string $path): ?ResolvedEndpoint
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));

        if (! $segments) {
            return null;
        }

        $groupId = null;
        $index = 0;
        $depthGuard = 0;

        while ($index < count($segments) && $depthGuard++ < 32) {
            $segment = $segments[$index];
            $next = $segments[$index + 1] ?? null;

            // An endpoint only matches when the next segment is its own secret.
            $endpoint = Endpoint::query()
                ->where('group_id', $groupId)
                ->where('slug', $segment)
                ->first();

            if ($endpoint && $next !== null && hash_equals($endpoint->secret, $next)) {
                return new ResolvedEndpoint(
                    endpoint: $endpoint,
                    suffix: implode('/', array_slice($segments, $index + 2)),
                );
            }

            $group = Group::query()
                ->where('parent_id', $groupId)
                ->where('slug', $segment)
                ->first();

            if ($group) {
                $groupId = $group->id;
                $index++;

                continue;
            }

            return null;
        }

        return null;
    }
}
