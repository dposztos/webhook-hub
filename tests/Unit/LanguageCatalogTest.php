<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guardrails for translation contributions.
 *
 * `lang/<code>.json` holds the UI strings for the Vue app; `lang/<code>/*.php`
 * holds the server-side ones. English is the reference: every other language
 * must carry the same keys with the same placeholders, so a partial or drifted
 * translation fails here rather than showing a raw key to a user.
 */
class LanguageCatalogTest extends TestCase
{
    private const REFERENCE = 'en';

    /** Resolved without the framework, so this stays a plain unit test. */
    private function langPath(string $suffix = ''): string
    {
        return dirname(__DIR__, 2).'/lang'.$suffix;
    }

    /** @return array<string, array<string, string>> */
    private function frontendCatalogs(): array
    {
        $catalogs = [];

        foreach (glob($this->langPath('/*.json')) ?: [] as $file) {
            $code = basename($file, '.json');
            $decoded = json_decode((string) file_get_contents($file), true);

            $this->assertIsArray($decoded, "lang/{$code}.json is not valid JSON");

            $catalogs[$code] = $decoded;
        }

        return $catalogs;
    }

    /** @param array<string, mixed> $array */
    private function flatten(array $array, string $prefix = ''): array
    {
        $flat = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /** @return array<int, string> */
    private function placeholders(string $text): array
    {
        // {name} for the frontend catalogs, :name for Laravel's translator.
        preg_match_all('/\{(\w+)\}|:(\w+)/', $text, $matches);

        $found = array_filter(array_merge($matches[1], $matches[2]));
        sort($found);

        return array_values(array_unique($found));
    }

    public function test_english_is_present_and_every_catalog_names_itself(): void
    {
        $catalogs = $this->frontendCatalogs();

        $this->assertArrayHasKey(self::REFERENCE, $catalogs, 'lang/en.json is the reference and must exist');

        foreach ($catalogs as $code => $catalog) {
            $this->assertArrayHasKey(
                '_name',
                $catalog,
                "lang/{$code}.json must have a \"_name\" key holding the language's own name, ".
                'because the language switcher lists it from there'
            );
        }
    }

    public function test_frontend_catalogs_have_the_same_keys_as_english(): void
    {
        $catalogs = $this->frontendCatalogs();
        $reference = $catalogs[self::REFERENCE];

        foreach ($catalogs as $code => $catalog) {
            if ($code === self::REFERENCE) {
                continue;
            }

            $missing = array_diff(array_keys($reference), array_keys($catalog));
            $extra = array_diff(array_keys($catalog), array_keys($reference));

            $this->assertSame([], array_values($missing), "lang/{$code}.json is missing keys");
            $this->assertSame([], array_values($extra), "lang/{$code}.json has keys English does not");
        }
    }

    public function test_frontend_placeholders_match_english(): void
    {
        $catalogs = $this->frontendCatalogs();
        $reference = $catalogs[self::REFERENCE];

        foreach ($catalogs as $code => $catalog) {
            if ($code === self::REFERENCE) {
                continue;
            }

            foreach ($catalog as $key => $text) {
                if ($key === '_name' || ! isset($reference[$key])) {
                    continue;
                }

                $this->assertSame(
                    $this->placeholders($reference[$key]),
                    $this->placeholders((string) $text),
                    "lang/{$code}.json key \"{$key}\" uses different placeholders than English"
                );
            }
        }
    }

    public function test_backend_catalogs_have_the_same_keys_as_english(): void
    {
        $reference = [];

        foreach (glob($this->langPath('/'.self::REFERENCE.'/*.php')) ?: [] as $file) {
            $reference[basename($file, '.php')] = $this->flatten(require $file);
        }

        $this->assertNotEmpty($reference, 'lang/en/*.php must exist');

        foreach (glob($this->langPath('/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $code = basename($directory);

            if ($code === self::REFERENCE) {
                continue;
            }

            foreach ($reference as $group => $strings) {
                $file = "{$directory}/{$group}.php";

                $this->assertFileExists($file, "lang/{$code}/{$group}.php is missing");

                $translated = $this->flatten(require $file);

                $this->assertSame(
                    [],
                    array_values(array_diff(array_keys($strings), array_keys($translated))),
                    "lang/{$code}/{$group}.php is missing keys"
                );

                foreach ($strings as $key => $text) {
                    if (! isset($translated[$key])) {
                        continue;
                    }

                    $this->assertSame(
                        $this->placeholders((string) $text),
                        $this->placeholders((string) $translated[$key]),
                        "lang/{$code}/{$group}.php key \"{$key}\" uses different placeholders than English"
                    );
                }
            }
        }
    }
}
