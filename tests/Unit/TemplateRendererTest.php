<?php

namespace Tests\Unit;

use App\Services\Templating\TemplateException;
use App\Services\Templating\TemplateRenderer;
use Tests\TestCase;

/**
 * Boots the application because the renderer resolves translated error messages
 * and the "money" filter's formatting from the container.
 */
class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    /** @var array<string, mixed> */
    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new TemplateRenderer;
        $this->context = [
            'json' => ['name' => 'Alex Doe', 'total' => 24990, 'items' => ['A-1', 'B-2']],
            'meta' => ['received_at_local' => '2026-08-14 10:00:00'],
        ];
    }

    public function test_interpolates_json_fields(): void
    {
        $this->assertSame('Hi Alex Doe!', $this->renderer->renderHtml('Hi {{ json.name }}!', $this->context));
    }

    public function test_a_missing_field_renders_empty_and_default_works(): void
    {
        $this->assertSame('[]', $this->renderer->renderHtml('[{{ json.missing }}]', $this->context));
        $this->assertSame('—', $this->renderer->renderHtml("{{ json.missing|default('—') }}", $this->context));
    }

    public function test_escapes_html_inside_captured_data(): void
    {
        $html = $this->renderer->renderHtml('{{ json.name }}', ['json' => ['name' => '<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_formatting_filters(): void
    {
        $this->assertSame('24,990', $this->renderer->renderHtml('{{ json.total|money }}', $this->context));
        $this->assertSame('24 990 Ft', $this->renderer->renderHtml('{{ json.total|money(" Ft", 0, ",", " ") }}', $this->context));
        $this->assertStringContainsString('<table', $this->renderer->renderHtml('{{ json.items|table }}', $this->context));
    }

    public function test_deprecated_hungarian_filters_still_work(): void
    {
        $this->assertSame('24 990 Ft', $this->renderer->renderHtml('{{ json.total|huf }}', $this->context));
        $this->assertSame(
            '2026.08.14. 10:00',
            $this->renderer->renderHtml('{{ meta.received_at_local|hu_date }}', $this->context)
        );
    }

    public function test_local_date_filter(): void
    {
        $this->assertSame(
            '2026-08-14',
            $this->renderer->renderHtml("{{ meta.received_at_local|local_date('Y-m-d') }}", $this->context)
        );
    }

    public function test_loops_and_conditionals(): void
    {
        $template = '{% for item in json.items %}{{ item }};{% endfor %}{% if json.total > 1000 %}big{% endif %}';

        $this->assertSame('A-1;B-2;big', $this->renderer->renderHtml($template, $this->context));
    }

    public function test_the_sandbox_blocks_disallowed_calls(): void
    {
        $this->expectException(TemplateException::class);

        $this->renderer->renderHtml('{{ dump(json) }}', $this->context);
    }

    public function test_gives_a_readable_error_for_a_broken_template(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/Template error/');

        $this->renderer->renderHtml('{% if %}', $this->context);
    }
}
