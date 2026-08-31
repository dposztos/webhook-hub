<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RuleAction extends Model
{
    protected $fillable = ['rule_id', 'type', 'name', 'enabled', 'position', 'config'];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    /**
     * The name is what a template addresses the step by, so it is stored in the
     * shape a template can actually use: no accents, no capitals, no spaces.
     * Normalising instead of rejecting keeps older rules — named "Python
     * szkript" by an earlier version of the editor — saveable.
     */
    public static function normalizeName(?string $name): ?string
    {
        $slug = Str::slug((string) $name, '_');

        return $slug === '' ? null : Str::limit($slug, 150, '');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
