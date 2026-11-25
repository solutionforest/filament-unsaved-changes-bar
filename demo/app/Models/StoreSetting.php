<?php

namespace Demo\App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'store_name' => 'Forest Coffee Co.',
            'support_email' => 'hello@forest-coffee.test',
            'currency' => 'HKD',
        ]);
    }
}
