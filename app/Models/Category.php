<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(EnvironmentalCase::class);
    }
}
