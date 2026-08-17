<?php

namespace Tests\Unit;

use App\Services\Rules\ConditionEvaluator;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator;
    }

    /** @param array<string, mixed> $json */
    private function ctx(array $json = [], array $headers = [], array $meta = []): array
    {
        return [
            'json' => $json,
            'body' => json_encode($json),
            'headers' => $headers,
            'query' => [],
            'meta' => $meta + ['method' => 'POST', 'ip' => '1.2.3.4', 'size' => 100],
        ];
    }

    private function cond(string $path, string $operator, mixed $value = null, array $extra = []): array
    {
        return array_merge([
            'type' => 'cond', 'source' => 'json', 'path' => $path, 'operator' => $operator, 'value' => $value,
        ], $extra);
    }

    private function group(string $op, array $children, bool $not = false): array
    {
        return ['type' => 'group', 'op' => $op, 'children' => $children, 'not' => $not];
    }

    private function check(array $node, array $context): bool
    {
        return $this->evaluator->evaluate($node, $context);
    }

    public function test_matches_every_message_when_there_are_no_conditions(): void
    {
        $this->assertTrue($this->check($this->group('and', []), $this->ctx(['a' => 1])));
    }

    public function test_egyenloseget_tipusfuggetlenul_kezel(): void
    {
        $context = $this->ctx(['status' => 'PAID', 'total' => '24990', 'aktiv' => true]);

        $this->assertTrue($this->check($this->group('and', [$this->cond('status', 'equals', 'PAID')]), $context));
        $this->assertFalse($this->check($this->group('and', [$this->cond('status', 'equals', 'paid')]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('status', 'equals', 'paid', ['ci' => true])]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('total', 'equals', 24990)]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('aktiv', 'is_true')]), $context));
    }

    public function test_szamokat_szamkent_hasonlit(): void
    {
        $this->assertTrue($this->check($this->group('and', [$this->cond('total', 'gt', '10000')]), $this->ctx(['total' => 24990])));
        $this->assertFalse($this->check($this->group('and', [$this->cond('total', 'gt', '10000')]), $this->ctx(['total' => '9 900'])));
        $this->assertTrue($this->check($this->group('and', [$this->cond('total', 'lte', 100)]), $this->ctx(['total' => 100])));

        // On a non-numeric value greater/less does not match, but must not throw either.
        $this->assertFalse($this->check($this->group('and', [$this->cond('total', 'gt', '10')]), $this->ctx(['total' => 'sok'])));
    }

    public function test_reaches_deeply_nested_and_array_fields(): void
    {
        $context = $this->ctx(['order' => ['items' => [['sku' => 'A-1'], ['sku' => 'B-2']]]]);

        $this->assertTrue($this->check($this->group('and', [$this->cond('order.items.1.sku', 'equals', 'B-2')]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('order.items.*.sku', 'contains', 'A-1')]), $context));
    }

    public function test_a_missing_field_is_not_confused_with_an_empty_one(): void
    {
        $context = $this->ctx(['a' => '', 'b' => null]);

        $this->assertTrue($this->check($this->group('and', [$this->cond('a', 'exists')]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('a', 'is_empty')]), $context));
        $this->assertFalse($this->check($this->group('and', [$this->cond('nincs', 'exists')]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('nincs', 'not_exists')]), $context));
        $this->assertTrue($this->check($this->group('and', [$this->cond('b', 'is_empty')]), $context));
    }

    public function test_fejlecet_kis_es_nagybetutol_fuggetlenul_talal(): void
    {
        $context = $this->ctx([], ['x-signature' => 'abc123']);

        $this->assertTrue($this->check(
            $this->group('and', [$this->cond('X-Signature', 'starts_with', 'abc', ['source' => 'header'])]),
            $context
        ));
    }

    public function test_nested_and_or_groups(): void
    {
        $context = $this->ctx(['event' => 'order.paid', 'total' => 500, 'orszag' => 'HU']);

        $rule = $this->group('and', [
            $this->cond('event', 'equals', 'order.paid'),
            $this->group('or', [
                $this->cond('total', 'gt', 10000),
                $this->cond('orszag', 'in', 'HU, SK'),
            ]),
        ]);

        $this->assertTrue($this->check($rule, $context));
        $this->assertFalse($this->check($this->group('and', [$rule], not: true), $context));
    }

    public function test_hibas_regularis_kifejezestol_nem_szall_el(): void
    {
        $this->assertFalse($this->check($this->group('and', [$this->cond('a', 'regex', '([')]), $this->ctx(['a' => 'x'])));
        $this->assertTrue($this->check($this->group('and', [$this->cond('a', 'regex', '^ORD-\d+$')]), $this->ctx(['a' => 'ORD-42'])));
    }

    public function test_produces_a_trace_of_the_decision(): void
    {
        $this->check($this->group('and', [$this->cond('total', 'gt', 10000)]), $this->ctx(['total' => 24990]));

        $this->assertCount(1, $this->evaluator->trace());
        $this->assertStringContainsString('igaz', $this->evaluator->trace()[0]);
    }
}
