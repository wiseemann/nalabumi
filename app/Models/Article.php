<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'url',
        'title',
        'content',
        'published_at',
        'source',
        'author',
        'image_url',
        'is_scraped',
        'is_analyzed',
        'has_embedding',
        'is_verified',
        'verified_by',
        'scraped_at',
        'analyzed_at',
        'verified_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_scraped' => 'boolean',
        'is_analyzed' => 'boolean',
        'has_embedding' => 'boolean',
        'is_verified' => 'boolean',
        'scraped_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(EnvironmentalCase::class);
    }

    public function verifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
