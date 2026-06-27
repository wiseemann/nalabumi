<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaReputation extends Model
{
    protected $fillable = [
        'name',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];
}
