<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserHolidayLocation extends Model
{
    protected $fillable = [
        'user_id', 'country_code', 'country_name', 'region_code', 'region_name',
        'city_code', 'city_name', 'consented_at',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function cacheKey(): string
    {
        return hash('sha256', mb_strtolower(implode('|', [
            $this->country_code,
            $this->region_code ?: $this->region_name,
            $this->city_code ?: $this->city_name,
        ])));
    }
}
