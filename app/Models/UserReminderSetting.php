<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReminderSetting extends Model
{
    use HasFactory;

    public const CONDITION_MISSING_ENTRY = 'missing_entry';
    public const CONDITION_MISSING_EXIT = 'missing_exit';
    public const CONDITION_MISSING_ENTRY_OR_EXIT = 'missing_entry_or_exit';
    public const CONDITION_MISSING_BOTH = 'missing_both';

    public const DAILY_CONDITIONS = [
        self::CONDITION_MISSING_ENTRY,
        self::CONDITION_MISSING_EXIT,
        self::CONDITION_MISSING_ENTRY_OR_EXIT,
        self::CONDITION_MISSING_BOTH,
    ];

    protected $fillable = [
        'user_id',
        'daily_enabled',
        'daily_condition',
        'daily_days_of_week',
        'send_time',
        'monthly_enabled',
        'yearly_enabled',
        'pdf_pin',
        'last_daily_sent_for_date',
        'last_monthly_sent_for_period',
        'last_yearly_sent_for_period',
    ];

    protected $casts = [
        'daily_enabled' => 'boolean',
        'daily_days_of_week' => 'array',
        'monthly_enabled' => 'boolean',
        'yearly_enabled' => 'boolean',
        'pdf_pin' => 'encrypted',
        'last_daily_sent_for_date' => 'date',
        'last_yearly_sent_for_period' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
