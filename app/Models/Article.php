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
        'scraped_at',
        'analyzed_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_scraped' => 'boolean',
        'is_analyzed' => 'boolean',
        'has_embedding' => 'boolean',
        'scraped_at' => 'datetime',
        'analyzed_at' => 'datetime',
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
}
