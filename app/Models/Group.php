<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'color', 'position'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class)->orderBy('position')->orderBy('name');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    /**
     * A gyökértől eddig a csoportig vezető lánc (a csoportot is beleértve).
     *
     * @return Collection<int, Group>
     */
    public function ancestors(): Collection
    {
        $chain = collect([$this]);
        $node = $this;
        $guard = 0;

        while ($node->parent_id && $guard++ < 32) {
            $node = $node->parent()->first();
            if (! $node) {
                break;
            }
            $chain->prepend($node);
        }

        return $chain;
    }

    /**
     * Slug-ok a gyökértől: ["ugyfelek", "abc123"].
     *
     * @return array<int, string>
     */
    public function pathSlugs(): array
    {
        return $this->ancestors()->pluck('slug')->all();
    }

    public function pathLabel(): string
    {
        return $this->ancestors()->pluck('name')->implode(' / ');
    }

    /**
     * Ennek a csoportnak és minden leszármazottjának az ID-i.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier) {
            $frontier = static::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
