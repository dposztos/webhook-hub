<?php

return [
    // Largest request body kept, in bytes. Anything above this is stored truncated.
    'max_body_bytes' => (int) env('WEBHOOK_MAX_BODY_BYTES', 512 * 1024),

    // Incoming requests per minute per IP address (0 = no limit).
    'ingest_rate_limit' => (int) env('WEBHOOK_INGEST_RATE_LIMIT', 600),

    // Default retention: null = forever. Each endpoint can override it.
    'default_retention_days' => env('WEBHOOK_RETENTION_DAYS') ? (int) env('WEBHOOK_RETENTION_DAYS') : null,

    // Ceiling on actions run for one message, as a guard against rule loops.
    'max_actions_per_message' => (int) env('WEBHOOK_MAX_ACTIONS', 20),

    'mail' => [
        // Restrict who actions may e-mail, if you need it: ["*@example.com"]
        'allowed_recipients' => array_filter(explode(',', (string) env('WEBHOOK_ALLOWED_RECIPIENTS', ''))),
    ],

    // Formatting used by the "money" template filter. The defaults are plain
    // English; a Hungarian setup would use ' Ft', ',' and ' '.
    'money' => [
        'decimals' => (int) env('WEBHOOK_MONEY_DECIMALS', 0),
        'decimal_separator' => env('WEBHOOK_MONEY_DECIMAL_SEPARATOR', '.'),
        'thousands_separator' => env('WEBHOOK_MONEY_THOUSANDS_SEPARATOR', ','),
        'suffix' => env('WEBHOOK_MONEY_SUFFIX', ''),
    ],
];
