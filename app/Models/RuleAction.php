<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleAction extends Model
{
    protected $fillable = ['rule_id', 'type', 'name', 'enabled', 'position', 'config'];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
