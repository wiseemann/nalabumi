<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentalCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'article_id',
        'location_id',
        'category_id',
        'cause',
        'impact',
        'severity',
        'confidence',
    ];

    protected $casts = [
        'severity' => 'integer',
        'confidence' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
