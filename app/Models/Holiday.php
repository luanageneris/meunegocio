<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['external_key', 'start_date', 'end_date', 'name', 'type', 'scope'];
}
