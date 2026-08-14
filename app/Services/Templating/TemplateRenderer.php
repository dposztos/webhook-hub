<?php

namespace App\Services\Templating;

use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFilter;

/**
 * Sablon-renderelés a beérkezett üzenet adataival.
 *
 * Twig fut, de homokozóban: csak engedélyezett tagek/filterek mennek, függvény- és
 * metódushívás nincs. Így a felületen szerkesztett sablon nem tud kitörni a kódba.
 */
class TemplateRenderer
{
    private ?Environment $html = null;

    private ?Environment $text = null;

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $template, array $context): string
    {
        return $this->render($this->htmlEnvironment(), $template, $context);
    }

    /**
     * Egysoros mezők (tárgy, címzett): itt nem kell HTML-escape.
     *
     * @param array<string, mixed> $context
     */
    public function renderText(string $template, array $context): string
    {
        return $this->render($this->textEnvironment(), $template, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws TemplateException
     */
    private function render(Environment $twig, string $template, array $context): string
    {
        if (trim($template) === '') {
            return '';
        }

        try {
            return $twig->createTemplate($template)->render($context);
        } catch (TwigError $e) {
            throw new TemplateException(
                sprintf('Sablonhiba (%d. sor): %s', $e->getTemplateLine(), $e->getRawMessage()),
                previous: $e
            );
        }
    }

    private function htmlEnvironment(): Environment
    {
        return $this->html ??= $this->makeEnvironment('html');
    }

    private function textEnvironment(): Environment
    {
        return $this->text ??= $this->makeEnvironment(false);
    }

    private function makeEnvironment(string|false $autoescape): Environment
    {
        $twig = new Environment(new ArrayLoader, [
            'autoescape' => $autoescape,
            'strict_variables' => false,
            'cache' => false,
            'debug' => false,
        ]);

        $policy = new SecurityPolicy(
            allowedTags: ['if', 'for', 'set', 'apply', 'verbatim', 'spaceless'],
            allowedFilters: [
                'abs', 'capitalize', 'default', 'escape', 'e', 'first', 'format', 'join', 'json_encode',
                'keys', 'last', 'length', 'lower', 'merge', 'nl2br', 'number_format', 'raw', 'replace',
                'reverse', 'round', 'slice', 'sort', 'split', 'striptags', 'title', 'trim', 'upper',
                'url_encode', 'date', 'column', 'filter', 'map', 'reduce',
                // saját filterek
                'huf', 'json_pretty', 'table', 'hu_date',
            ],
            allowedMethods: [],
            allowedProperties: [],
            allowedFunctions: ['range', 'max', 'min', 'date', 'random'],
        );

        $twig->addExtension(new SandboxExtension($policy, sandboxed: true));

        foreach ($this->filters() as $filter) {
            $twig->addFilter($filter);
        }

        return $twig;
    }

    /**
     * @return array<int, TwigFilter>
     */
    private function filters(): array
    {
        return [
            // 24990 → "24 990 Ft"
            new TwigFilter('huf', function (mixed $value, string $suffix = ' Ft'): string {
                if (! is_numeric($value)) {
                    return (string) $value;
                }

                return number_format((float) $value, 0, ',', ' ').$suffix;
            }),

            // Tetszőleges érték olvasható JSON-ként
            new TwigFilter('json_pretty', function (mixed $value): string {
                return (string) json_encode(
                    $value,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }),

            // Dátum magyar formában
            new TwigFilter('hu_date', function (mixed $value, string $format = 'Y.m.d. H:i'): string {
                if ($value === null || $value === '') {
                    return '';
                }

                $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

                return $timestamp === false ? (string) $value : date($format, $timestamp);
            }),

            // Kulcs-érték tömb egyszerű HTML-táblázatként (levélbe beilleszthető)
            new TwigFilter('table', function (mixed $value): string {
                return (new HtmlTable)->render($value);
            }, ['is_safe' => ['html']]),
        ];
    }
}
