<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidaySync extends Model
{
    protected $fillable = ['location_key', 'year', 'source', 'status', 'synced_at', 'attempted_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime', 'attempted_at' => 'datetime'];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
