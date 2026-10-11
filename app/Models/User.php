<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const MASTER_ADMIN_EMAIL = 'admin@meunegocio.cloud';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'role',
        'company_id',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => 'string',
            'last_login_at' => 'datetime',
        ];
    }

    // Relacionamentos
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ownedCompanies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    public function importLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ImportLog::class, 'imported_by');
    }

    public function reminderSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserReminderSetting::class);
    }

    // Helpers
    public function isRoot(): bool
    {
        return $this->role === 'root';
    }

    public function isMasterAdmin(): bool
    {
        return strtolower((string) $this->email) === strtolower(self::MASTER_ADMIN_EMAIL);
    }
}
