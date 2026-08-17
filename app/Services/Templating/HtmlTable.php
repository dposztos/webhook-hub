<?php

namespace App\Services\Templating;

/**
 * Tömb → egyszerű, levélbe illeszthető HTML-táblázat (inline stílusokkal,
 * mert a levelezőkliensek egy része kidobja a <style> blokkot).
 */
class HtmlTable
{
    private const TD = 'padding:6px 10px;border:1px solid #e2e8f0;font:14px -apple-system,Segoe UI,Roboto,sans-serif;vertical-align:top';

    private const TH = self::TD.';background:#f8fafc;font-weight:600;text-align:left;white-space:nowrap';

    public function render(mixed $value, int $depth = 0): string
    {
        if (! is_array($value)) {
            return $this->escape($value);
        }

        if ($depth > 4) {
            return '<code>…</code>';
        }

        $rows = '';

        foreach ($value as $key => $item) {
            $rows .= sprintf(
                '<tr><th style="%s">%s</th><td style="%s">%s</td></tr>',
                self::TH,
                htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                self::TD,
                is_array($item) ? $this->render($item, $depth + 1) : $this->escape($item)
            );
        }

        if ($rows === '') {
            return '<em>üres</em>';
        }

        return '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:4px 0">'.$rows.'</table>';
    }

    private function escape(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        if ($value === null) {
            $value = '—';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
