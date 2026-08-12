<?php

namespace MltStephane\LaravelAnalytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Visitor extends Model
{
    use HasUlids;

    protected $table = 'analytics_visitors';

    protected $fillable = [
        'uuid',
        'browser',
        'browser_version',
        'os',
        'device',
        'device_type',
        'language',
        'country',
        'region',
        'city',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActiveSince(Builder $query, Carbon $since): Builder
    {
        return $query->where('last_seen_at', '>=', $since);
    }
}
