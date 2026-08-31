<?php

return [
    'console' => [
        'admin_description' => 'Create an admin user or change their password',
        'admin_email' => 'E-mail address',
        'admin_password' => 'Password',
        'admin_required' => 'E-mail and password are both required.',
        'admin_password_short' => 'The password must be at least 10 characters.',
        'admin_created' => 'Admin created: :email',
        'admin_updated' => 'Password updated: :email',

        'prune_dry_run' => 'Only count, do not delete',
        'prune_description' => 'Apply the per-endpoint retention rules (default: keep everything forever)',
        'prune_marked' => 'Marked for deletion: :count message(s) (dry run)',
        'prune_deleted' => 'Deleted: :count message(s)',

        'recount_description' => 'Recalculate endpoint message counters from the stored messages',
        'recount_fixed' => 'Fixed the counter on :count endpoint(s)',
        'recount_ok' => 'Every counter was already accurate.',
    ],

    'validation' => [
        'group_cycle' => 'A group cannot be moved under itself (or under its own descendant).',
        'rule_scope' => 'A rule belongs either to a group or to an endpoint — not to both.',
    ],

    'conditions' => [
        'root' => 'conditions',
        'too_deep' => 'Conditions are nested too deeply.',
        'bad_operator_join' => ':path: the join must be either AND or OR.',
        'bad_child' => ':path: invalid element at position :index.',
        'unknown_source' => ':path: unknown field source (:source).',
        'unknown_operator' => ':path: unknown operator (:operator).',
        'path_required' => ':path: the field name is required.',
        'bad_regex' => ':path: invalid regular expression.',
    ],

    'actions' => [
        'unknown_type' => 'Unknown action type: :type',
        'limit_reached' => 'Action limit reached (:limit actions per message)',
        'rule_failed' => 'Rule evaluation crashed',
        'previous_failed' => 'Skipped, because the previous step :status.',
        'no_previous' => 'does not exist',
        'failed_summary' => 'Failed',
        'status' => [
            'success' => 'succeeded',
            'failed' => 'failed',
            'skipped' => 'was skipped',
        ],
    ],

    'email' => [
        'no_recipient' => 'No valid recipient (the "to" template resolved to an empty or malformed address).',
        'default_subject' => 'Webhook notification',
        'recipient_blocked' => 'Recipient not allowed (WEBHOOK_ALLOWED_RECIPIENTS): :addresses',
        'dry_run' => 'Dry run — no mail was sent',
        'sent' => 'Sent: :addresses',
    ],

    'script' => [
        'disabled' => 'Script actions are switched off (WEBHOOK_SCRIPTS_ENABLED).',
        'inline_disabled' => 'Inline code is switched off (WEBHOOK_SCRIPTS_ALLOW_INLINE); pick a file from the script directory instead.',
        'no_directory' => 'The script directory does not exist: :dir',
        'bad_path' => 'Invalid script path: :path',
        'not_found' => 'Script not found in the script directory: :path',
        'not_python' => 'Only .py files can be run: :path',
        'no_code' => 'The inline script is empty.',
        'temp_failed' => 'Could not write the temporary script file into :dir',
        'dry_run' => 'Dry run — the script was not started',
        'ok' => 'Ran: :name',
        'exit_code' => 'The script exited with code :code — :error',
        'timeout' => 'The script was killed after :seconds seconds.',
        'truncated' => 'output truncated',
    ],

    'rules' => [
        'all_endpoints' => 'Every endpoint',
    ],

    'template' => [
        'error' => 'Template error (line :line): :message',
        'empty_table' => 'empty',
    ],
];
