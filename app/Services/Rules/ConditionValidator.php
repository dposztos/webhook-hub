<?php

namespace App\Services\Rules;

/**
 * Structural check of the condition tree coming from the UI, run before saving.
 */
class ConditionValidator
{
    /**
     * @param  array<string, mixed>  $node
     * @return array<int, string> error messages (empty array = valid)
     */
    public function validate(array $node, int $depth = 0, ?string $path = null): array
    {
        $path ??= __('webhookhub.conditions.root');

        if ($depth > 16) {
            return [__('webhookhub.conditions.too_deep')];
        }

        $type = $node['type'] ?? 'group';

        if ($type === 'group') {
            $errors = [];

            if (! in_array(strtolower((string) ($node['op'] ?? 'and')), ['and', 'or'], true)) {
                $errors[] = __('webhookhub.conditions.bad_operator_join', ['path' => $path]);
            }

            foreach ((array) ($node['children'] ?? []) as $i => $child) {
                if (! is_array($child)) {
                    $errors[] = __('webhookhub.conditions.bad_child', ['path' => $path, 'index' => $i]);

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
            $errors[] = __('webhookhub.conditions.unknown_source', ['path' => $path, 'source' => $source]);
        }

        if (! in_array($operator, ConditionEvaluator::OPERATORS, true)) {
            $errors[] = __('webhookhub.conditions.unknown_operator', ['path' => $path, 'operator' => $operator]);
        }

        if ($source !== 'body' && trim((string) ($node['path'] ?? '')) === '') {
            $errors[] = __('webhookhub.conditions.path_required', ['path' => $path]);
        }

        if (in_array($operator, ['regex', 'not_regex'], true)) {
            $pattern = '#'.str_replace('#', '\#', (string) ($node['value'] ?? '')).'#u';
            set_error_handler(fn () => true);
            $valid = @preg_match($pattern, '') !== false;
            restore_error_handler();

            if (! $valid) {
                $errors[] = __('webhookhub.conditions.bad_regex', ['path' => $path]);
            }
        }

        return $errors;
    }
}
