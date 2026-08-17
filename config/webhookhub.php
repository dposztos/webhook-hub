<?php

return [
    // A tárolt kérés-test maximális mérete bájtban (efölött csonkolva tároljuk).
    'max_body_bytes' => (int) env('WEBHOOK_MAX_BODY_BYTES', 512 * 1024),

    // Beérkező kérések percenkénti korlátja IP-nként (0 = nincs korlát).
    'ingest_rate_limit' => (int) env('WEBHOOK_INGEST_RATE_LIMIT', 600),

    // Alapértelmezett megőrzés: null = örökre. Endpointonként felülírható.
    'default_retention_days' => env('WEBHOOK_RETENTION_DAYS') ? (int) env('WEBHOOK_RETENTION_DAYS') : null,

    // Egy üzenetre futtatható akciók maximális száma (védelem szabály-hurok ellen).
    'max_actions_per_message' => (int) env('WEBHOOK_MAX_ACTIONS', 20),

    'mail' => [
        // Az akciókban megadható címzettek korlátozása, ha kell: ["*@ceg.hu"]
        'allowed_recipients' => array_filter(explode(',', (string) env('WEBHOOK_ALLOWED_RECIPIENTS', ''))),
    ],
];
