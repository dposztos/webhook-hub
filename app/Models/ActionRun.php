<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionRun extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'detail' => 'array',
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(RuleAction::class, 'rule_action_id');
    }
}
