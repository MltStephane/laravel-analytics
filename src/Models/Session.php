<?php

namespace MltStephane\LaravelAnalytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Session extends Model
{
    use HasUlids;

    protected $table = 'analytics_sessions';

    protected $fillable = [
        'visitor_id',
        'hostname',
        'path',
        'referrer',
        'referrer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'started_at',
        'last_activity_at',
        'duration',
        'bounced',
        'pages_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'duration' => 'int',
            'bounced' => 'bool',
            'pages_count' => 'int',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActiveSince(Builder $query, Carbon $since): Builder
    {
        return $query->where('last_activity_at', '>=', $since);
    }
}
