<?php

namespace Tests\Unit;

use App\Services\Templating\TemplateException;
use App\Services\Templating\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    /** @var array<string, mixed> */
    private array $context;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer;
        $this->context = [
            'json' => ['nev' => 'Teszt Elek', 'osszeg' => 24990, 'tetelek' => ['A-1', 'B-2']],
            'meta' => ['received_at_hu' => '2026.08.14. 10:00:00'],
        ];
    }

    public function test_behelyettesiti_a_json_mezoket(): void
    {
        $this->assertSame('Szia Teszt Elek!', $this->renderer->renderHtml('Szia {{ json.nev }}!', $this->context));
    }

    public function test_hianyzo_mezo_uresen_marad_es_a_default_mukodik(): void
    {
        $this->assertSame('[]', $this->renderer->renderHtml('[{{ json.nincs }}]', $this->context));
        $this->assertSame('—', $this->renderer->renderHtml("{{ json.nincs|default('—') }}", $this->context));
    }

    public function test_html_t_escapel_a_beerkezett_adatban(): void
    {
        $html = $this->renderer->renderHtml('{{ json.nev }}', ['json' => ['nev' => '<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_magyar_formazo_szurok(): void
    {
        $this->assertSame('24 990 Ft', $this->renderer->renderHtml('{{ json.osszeg|huf }}', $this->context));
        $this->assertStringContainsString('<table', $this->renderer->renderHtml('{{ json.tetelek|table }}', $this->context));
    }

    public function test_ciklus_es_feltetel(): void
    {
        $template = '{% for tetel in json.tetelek %}{{ tetel }};{% endfor %}{% if json.osszeg > 1000 %}nagy{% endif %}';

        $this->assertSame('A-1;B-2;nagy', $this->renderer->renderHtml($template, $this->context));
    }

    public function test_a_homokozo_megallitja_a_nem_engedelyezett_hivasokat(): void
    {
        $this->expectException(TemplateException::class);

        $this->renderer->renderHtml('{{ dump(json) }}', $this->context);
    }

    public function test_ertheto_hibauzenet_rossz_sablonra(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/Sablonhiba/');

        $this->renderer->renderHtml('{% if %}', $this->context);
    }
}
