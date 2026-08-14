<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'query' => 'array',
        'headers' => 'array',
        'body_json' => 'array',
        'files' => 'array',
        'matched_rules' => 'array',
        'truncated' => 'boolean',
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(ActionRun::class)->orderBy('id');
    }

    /**
     * Rövid előnézet a listához: az első pár mező a JSON-ból, vagy a nyers test eleje.
     */
    public function preview(int $limit = 140): string
    {
        if (is_array($this->body_json)) {
            $flat = [];
            foreach ($this->body_json as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $flat[] = $key.'='.var_export($value, true);
                }
                if (count($flat) >= 4) {
                    break;
                }
            }
            if ($flat) {
                return mb_strimwidth(implode(' ', $flat), 0, $limit, '…');
            }
        }

        return mb_strimwidth(trim((string) $this->body), 0, $limit, '…');
    }
}
