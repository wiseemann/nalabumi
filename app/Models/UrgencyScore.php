<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrgencyScore extends Model
{
    protected $fillable = [
        'location_id',
        'score',
        'level',
        'case_count',
        'avg_severity',
        'date',
    ];

    protected $casts = [
        'score' => 'integer',
        'case_count' => 'integer',
        'avg_severity' => 'integer',
        'date' => 'date',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function getLevelColorAttribute(): string
    {
        return match($this->level) {
            'rendah' => 'green',
            'sedang' => 'yellow',
            'tinggi' => 'orange',
            'kritis' => 'red',
            default => 'gray',
        };
    }

    public function getLevelHexColorAttribute(): string
    {
        return match($this->level) {
            'rendah' => '#22c55e',
            'sedang' => '#eab308',
            'tinggi' => '#f97316',
            'kritis' => '#ef4444',
            default => '#6b7280',
        };
    }
}
