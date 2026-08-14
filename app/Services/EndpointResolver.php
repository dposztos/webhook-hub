<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\Group;

/**
 * A beérkező URL útvonalát bontja fel: csoport-lánc / endpoint / titok / maradék.
 *
 * Példa: "ugyfelek/abc123/rendelesek/k7f3q9x2mnpq/extra/utvonal"
 *   → endpoint = "rendelesek" az "ugyfelek/abc123" csoportban, suffix = "extra/utvonal"
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

            // Endpoint akkor nyer, ha a rákövetkező szegmens a hozzá tartozó titok.
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
