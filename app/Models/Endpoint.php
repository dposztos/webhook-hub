<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'name', 'slug', 'description', 'position', 'enabled',
        'response_status', 'response_body', 'response_content_type', 'response_delay_ms', 'cors',
        'retention_days', 'max_messages',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'cors' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Endpoint $endpoint) {
            $endpoint->uuid ??= (string) Str::uuid();
            $endpoint->secret ??= static::newSecret();
        });
    }

    public static function newSecret(): string
    {
        // Kisbetű+szám, összetéveszthető karakterek nélkül (0/O, 1/l/I).
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    /**
     * Az URL útvonal-része: "ugyfelek/abc123/rendelesek/k7f3q9x2mnpq".
     */
    public function pathWithSecret(): string
    {
        $parts = $this->group ? $this->group->pathSlugs() : [];
        $parts[] = $this->slug;
        $parts[] = $this->secret;

        return implode('/', $parts);
    }

    public function url(): string
    {
        return rtrim(config('app.url'), '/').'/u/'.$this->pathWithSecret();
    }

    /**
     * A tárolt üzenetszámláló újraszámolása.
     *
     * A számláló denormalizált (beérkezéskor növeljük), törléskor viszont
     * a tömeges törlések nem váltanak ki modell-eseményt – ezért számoljuk újra.
     */
    public function recountMessages(): void
    {
        $this->forceFill([
            'messages_count' => Message::where('endpoint_id', $this->id)->count(),
        ])->save();
    }

    /**
     * A csoport-hierarchia ID-i a gyökértől (szabály-öröklődéshez).
     *
     * @return array<int, int>
     */
    public function groupChainIds(): array
    {
        if (! $this->group) {
            return [];
        }

        return $this->group->ancestors()->pluck('id')->all();
    }
}
