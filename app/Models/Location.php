<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    protected $fillable = [
        'name',
        'regency_name',
        'province_name',
        'latitude',
        'longitude',
        'geojson_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(EnvironmentalCase::class);
    }

    public function urgencyScores(): HasMany
    {
        return $this->hasMany(UrgencyScore::class);
    }

    public function latestUrgencyScore(): HasOne
    {
        return $this->hasOne(UrgencyScore::class)->latestOfMany('date');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->regency_name ?? $this->name;
    }

    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([$this->regency_name, $this->province_name]);
        return implode(', ', $parts);
    }
}
