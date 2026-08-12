<?php

namespace MltStephane\LaravelAnalytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MltStephane\LaravelAnalytics\Enums\EventType;

class Event extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $fillable = [
        'visitor_id',
        'session_id',
        'type',
        'name',
        'url',
        'title',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function scopeOfType(Builder $query, EventType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeActiveSince(Builder $query, Carbon $since): Builder
    {
        return $query->where('created_at', '>=', $since);
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
