<?php

namespace App\Services\Rules;

/**
 * Evaluates the condition tree.
 *
 * Group node:      {"type":"group","op":"and|or","not":false,"children":[...]}
 * Condition node:  {"type":"cond","source":"json","path":"a.b","operator":"equals","value":"x","ci":false}
 *
 * An empty and-group evaluates to true: a rule without conditions runs for every message.
 */
class ConditionEvaluator
{
    public const OPERATORS = [
        'equals', 'not_equals',
        'contains', 'not_contains',
        'starts_with', 'ends_with',
        'regex', 'not_regex',
        'gt', 'gte', 'lt', 'lte',
        'in', 'not_in',
        'exists', 'not_exists',
        'is_empty', 'is_not_empty',
        'is_true', 'is_false',
    ];

    /** @var array<int, string> Explanation of the evaluation, for debugging and tests. */
    private array $trace = [];

    public function __construct(private readonly ValueResolver $resolver = new ValueResolver) {}

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    public function evaluate(array $node, array $context): bool
    {
        $this->trace = [];

        return $this->node($node, $context);
    }

    /** @return array<int, string> */
    public function trace(): array
    {
        return $this->trace;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    private function node(array $node, array $context, int $depth = 0): bool
    {
        if ($depth > 16) {
            return false;
        }

        $type = $node['type'] ?? 'group';
        $result = $type === 'group'
            ? $this->group($node, $context, $depth)
            : $this->condition($node, $context);

        return ! empty($node['not']) ? ! $result : $result;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    private function group(array $node, array $context, int $depth): bool
    {
        $op = strtolower((string) ($node['op'] ?? 'and'));
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        if (! $children) {
            return $op === 'and';
        }

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $value = $this->node($child, $context, $depth + 1);

            if ($op === 'and' && ! $value) {
                return false;
            }

            if ($op === 'or' && $value) {
                return true;
            }
        }

        return $op === 'and';
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    private function condition(array $node, array $context): bool
    {
        $source = (string) ($node['source'] ?? 'json');
        $path = (string) ($node['path'] ?? '');
        $operator = (string) ($node['operator'] ?? 'equals');
        $expected = $node['value'] ?? null;
        $ci = (bool) ($node['ci'] ?? false);

        if (! in_array($source, ValueResolver::SOURCES, true)) {
            return false;
        }

        $exists = $this->resolver->exists($context, $source, $path);
        $actual = $this->resolver->resolve($context, $source, $path);

        $result = match ($operator) {
            'exists' => $exists,
            'not_exists' => ! $exists,
            'is_empty' => ! $exists || $this->isEmpty($actual),
            'is_not_empty' => $exists && ! $this->isEmpty($actual),
            'is_true' => $this->truthy($actual),
            'is_false' => ! $this->truthy($actual),
            'equals' => $this->equals($actual, $expected, $ci),
            'not_equals' => ! $this->equals($actual, $expected, $ci),
            'contains' => $this->contains($actual, $expected, $ci),
            'not_contains' => ! $this->contains($actual, $expected, $ci),
            'starts_with' => $this->affix($actual, $expected, $ci, 'start'),
            'ends_with' => $this->affix($actual, $expected, $ci, 'end'),
            'regex' => $this->regex($actual, $expected, $ci),
            'not_regex' => ! $this->regex($actual, $expected, $ci),
            'gt', 'gte', 'lt', 'lte' => $this->compare($actual, $expected, $operator),
            'in' => $this->inList($actual, $expected, $ci),
            'not_in' => ! $this->inList($actual, $expected, $ci),
            default => false,
        };

        $this->trace[] = sprintf(
            '%s.%s (%s) %s %s → %s',
            $source,
            $path === '' ? '*' : $path,
            $this->short($actual),
            $operator,
            $this->short($expected),
            $result ? 'igaz' : 'hamis'
        );

        return $result;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value != 0.0;
        }

        return in_array(strtolower(trim((string) $value)), ['true', 'yes', 'igen', '1', 'on'], true);
    }

    private function equals(mixed $actual, mixed $expected, bool $ci): bool
    {
        if (is_array($actual)) {
            // For an array, "equals" means "contains this value".
            return $this->inList($expected, $actual, $ci);
        }

        if (is_bool($actual) || is_bool($expected)) {
            return $this->truthy($actual) === $this->truthy($expected);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual == (float) $expected;
        }

        return $this->str($actual, $ci) === $this->str($expected, $ci);
    }

    private function contains(mixed $actual, mixed $expected, bool $ci): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->equals($item, $expected, $ci)) {
                    return true;
                }
            }

            return false;
        }

        $needle = $this->str($expected, $ci);

        return $needle !== '' && str_contains($this->str($actual, $ci), $needle);
    }

    private function affix(mixed $actual, mixed $expected, bool $ci, string $where): bool
    {
        $needle = $this->str($expected, $ci);
        $haystack = $this->str($actual, $ci);

        if ($needle === '') {
            return false;
        }

        return $where === 'start'
            ? str_starts_with($haystack, $needle)
            : str_ends_with($haystack, $needle);
    }

    private function regex(mixed $actual, mixed $expected, bool $ci): bool
    {
        $pattern = (string) $expected;

        if ($pattern === '') {
            return false;
        }

        $delimited = '#'.str_replace('#', '\#', $pattern).'#u'.($ci ? 'i' : '');

        set_error_handler(fn () => true);
        $match = preg_match($delimited, $this->scalarString($actual));
        restore_error_handler();

        return $match === 1;
    }

    private function compare(mixed $actual, mixed $expected, string $operator): bool
    {
        $left = $this->numeric($actual);
        $right = $this->numeric($expected);

        if ($left === null || $right === null) {
            return false;
        }

        return match ($operator) {
            'gt' => $left > $right,
            'gte' => $left >= $right,
            'lt' => $left < $right,
            'lte' => $left <= $right,
            default => false,
        };
    }

    /**
     * A value comparable either as a number or as a point in time.
     */
    private function numeric(mixed $value): ?float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        // Numbers written as "1 234,56" or "1,234.56"
        $normalized = str_replace([' ', ' '], '', $string);
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $normalized)) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})*(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        }

        if (is_numeric($normalized)) {
            return (float) $normalized;
        }

        $timestamp = strtotime($string);

        return $timestamp === false ? null : (float) $timestamp;
    }

    private function inList(mixed $actual, mixed $expected, bool $ci): bool
    {
        $list = is_array($expected)
            ? $expected
            : array_map('trim', explode(',', (string) $expected));

        foreach ($list as $item) {
            if ($this->equals($actual, $item, $ci)) {
                return true;
            }
        }

        return false;
    }

    private function str(mixed $value, bool $ci): string
    {
        $string = $this->scalarString($value);

        return $ci ? mb_strtolower($string) : $string;
    }

    private function scalarString(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function short(mixed $value): string
    {
        return mb_strimwidth($this->scalarString($value), 0, 60, '…');
    }
}
