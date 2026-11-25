<?php

namespace Demo\App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_featured' => 'boolean',
        'published_at' => 'date',
        'tags' => 'array',
        'highlights' => 'array',
    ];
}
