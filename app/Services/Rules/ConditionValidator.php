<?php

namespace App\Services\Rules;

/**
 * A felületről érkező feltétel-fa szerkezeti ellenőrzése mentés előtt.
 */
class ConditionValidator
{
    /**
     * @param array<string, mixed> $node
     * @return array<int, string> hibaüzenetek (üres tömb = rendben)
     */
    public function validate(array $node, int $depth = 0, string $path = 'feltételek'): array
    {
        if ($depth > 16) {
            return ['Túl mélyen egymásba ágyazott feltételek.'];
        }

        $type = $node['type'] ?? 'group';

        if ($type === 'group') {
            $errors = [];

            if (! in_array(strtolower((string) ($node['op'] ?? 'and')), ['and', 'or'], true)) {
                $errors[] = "{$path}: az összekötés csak ÉS vagy VAGY lehet.";
            }

            foreach ((array) ($node['children'] ?? []) as $i => $child) {
                if (! is_array($child)) {
                    $errors[] = "{$path}: érvénytelen elem a(z) {$i}. helyen.";

                    continue;
                }

                $errors = array_merge($errors, $this->validate($child, $depth + 1, "{$path}[{$i}]"));
            }

            return $errors;
        }

        $errors = [];
        $source = (string) ($node['source'] ?? '');
        $operator = (string) ($node['operator'] ?? '');

        if (! in_array($source, ValueResolver::SOURCES, true)) {
            $errors[] = "{$path}: ismeretlen mezőforrás ({$source}).";
        }

        if (! in_array($operator, ConditionEvaluator::OPERATORS, true)) {
            $errors[] = "{$path}: ismeretlen operátor ({$operator}).";
        }

        if ($source !== 'body' && trim((string) ($node['path'] ?? '')) === '') {
            $errors[] = "{$path}: a mező neve kötelező.";
        }

        if (in_array($operator, ['regex', 'not_regex'], true)) {
            $pattern = '#'.str_replace('#', '\#', (string) ($node['value'] ?? '')).'#u';
            set_error_handler(fn () => true);
            $valid = @preg_match($pattern, '') !== false;
            restore_error_handler();

            if (! $valid) {
                $errors[] = "{$path}: érvénytelen reguláris kifejezés.";
            }
        }

        return $errors;
    }
}
