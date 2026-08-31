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

    'scripts' => [
        // Master switch. Running scripts from a rule is remote code execution by
        // design: anyone who can log in to the UI can make the server run code.
        // It stays off until you turn it on deliberately.
        'enabled' => filter_var(env('WEBHOOK_SCRIPTS_ENABLED', false), FILTER_VALIDATE_BOOL),

        // The interpreter. An absolute path is safest: "/usr/bin/python3" in the
        // container, "C:\\Python312\\python.exe" on a native Windows install.
        'python' => env('WEBHOOK_PYTHON_BIN', 'python3'),

        // The only directory scripts may be run from. A rule can pick a file
        // inside it (subdirectories included), never outside it.
        'dir' => env('WEBHOOK_SCRIPTS_DIR', base_path('scripts')),

        // Whether a rule may carry its own inline code instead of pointing at a
        // file. Convenient, and a second thing to weigh: with this on, the UI is
        // a code editor that runs on the server.
        'allow_inline' => filter_var(env('WEBHOOK_SCRIPTS_ALLOW_INLINE', false), FILTER_VALIDATE_BOOL),

        // Default and hard ceiling for a single run, in seconds. A script that
        // overruns is killed and the action is recorded as failed.
        'timeout' => (int) env('WEBHOOK_SCRIPT_TIMEOUT', 30),
        'max_timeout' => (int) env('WEBHOOK_SCRIPT_MAX_TIMEOUT', 300),

        // How much of stdout/stderr is kept per run. The rest is dropped, so a
        // chatty script cannot fill the database.
        'max_output_bytes' => (int) env('WEBHOOK_SCRIPT_MAX_OUTPUT', 64 * 1024),

        // Install "requirements.txt" from the script directory on start, into
        // the virtualenv below, and run scripts with that interpreter. Lets a
        // new library arrive with a restart instead of a rebuilt image.
        'requirements' => filter_var(env('WEBHOOK_SCRIPTS_REQUIREMENTS', false), FILTER_VALIDATE_BOOL),

        // Where that virtualenv lives. Put it on a volume, or every recreated
        // container reinstalls from scratch.
        'venv' => env('WEBHOOK_SCRIPTS_VENV', storage_path('pyenv')),
    ],

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
