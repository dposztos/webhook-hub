<?php

namespace App\Services\Templating;

use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFilter;

/**
 * Renders a template against the data of a captured message.
 *
 * Twig runs sandboxed: only allow-listed tags and filters, no function or method
 * calls. A template edited in the UI therefore cannot reach into the code.
 */
class TemplateRenderer
{
    private ?Environment $html = null;

    private ?Environment $text = null;

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderHtml(string $template, array $context): string
    {
        return $this->render($this->htmlEnvironment(), $template, $context);
    }

    /**
     * Single-line fields (subject, recipient) — no HTML escaping wanted here.
     *
     * @param  array<string, mixed>  $context
     */
    public function renderText(string $template, array $context): string
    {
        return $this->render($this->textEnvironment(), $template, $context);
    }

    /**
     * @param  array<string, mixed>  $context
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
                __('webhookhub.template.error', [
                    'line' => $e->getTemplateLine(),
                    'message' => $e->getRawMessage(),
                ]),
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
                // filters defined below
                'money', 'json_pretty', 'table', 'local_date',
                // deprecated Hungarian-specific aliases, kept so older templates keep working
                'huf', 'hu_date',
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
        $money = function (
            mixed $value,
            ?string $suffix = null,
            ?int $decimals = null,
            ?string $decimalSeparator = null,
            ?string $thousandsSeparator = null,
        ): string {
            if (! is_numeric($value)) {
                return (string) $value;
            }

            $format = (array) config('webhookhub.money');

            return number_format(
                (float) $value,
                $decimals ?? (int) ($format['decimals'] ?? 0),
                $decimalSeparator ?? (string) ($format['decimal_separator'] ?? '.'),
                $thousandsSeparator ?? (string) ($format['thousands_separator'] ?? ','),
            ).($suffix ?? (string) ($format['suffix'] ?? ''));
        };

        // Formats in the app timezone, unlike Twig's built-in date filter which
        // uses whatever the value carries.
        $localDate = function (mixed $value, string $format = 'Y-m-d H:i'): string {
            if ($value === null || $value === '') {
                return '';
            }

            $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

            return $timestamp === false ? (string) $value : date($format, $timestamp);
        };

        return [
            // 24990 → "24,990" (see the "money" block in config/webhookhub.php)
            new TwigFilter('money', $money),

            // Any value rendered as readable JSON
            new TwigFilter('json_pretty', function (mixed $value): string {
                return (string) json_encode(
                    $value,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }),

            new TwigFilter('local_date', $localDate),

            // Key-value array as a simple HTML table that can be pasted into mail
            new TwigFilter('table', function (mixed $value): string {
                return (new HtmlTable)->render($value);
            }, ['is_safe' => ['html']]),

            // Deprecated aliases from the Hungarian-only era; still honoured so
            // that templates saved before the rename keep rendering.
            new TwigFilter('huf', fn (mixed $value, string $suffix = ' Ft'): string => is_numeric($value)
                ? number_format((float) $value, 0, ',', ' ').$suffix
                : (string) $value),
            new TwigFilter('hu_date', fn (mixed $value, string $format = 'Y.m.d. H:i'): string => $localDate($value, $format)),
        ];
    }
}
