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

    public function cases()
    {
        return $this->hasMany(EnvironmentalCase::class);
    }

    /**
     * Menghitung total wilayah terdampak dengan cara yang sama persis seperti
     * algoritma peta di Dashboard (menyatukan Kabupaten/Kota menjadi 1 entitas).
     */
    public static function countNormalizedLocationsWithCases(): int
    {
        return self::has('cases')->get()->map(function ($loc) {
            $name = $loc->regency_name ?: $loc->name;
            return strtolower(trim(preg_replace('/^(Kabupaten|Kota|Kecamatan)\s+/i', '', $name ?? '')));
        })->filter()->unique()->count();
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
