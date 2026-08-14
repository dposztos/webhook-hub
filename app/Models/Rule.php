<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    protected $fillable = [
        'name', 'description', 'enabled', 'priority',
        'group_id', 'endpoint_id', 'conditions', 'stop_processing',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'stop_processing' => 'boolean',
        'conditions' => 'array',
        'last_matched_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RuleAction::class)->orderBy('position')->orderBy('id');
    }

    public function scopeType(): string
    {
        return $this->endpoint_id ? 'endpoint' : ($this->group_id ? 'group' : 'global');
    }
}
