<?php

namespace App\Models;

use App\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'supervisor_id',
        'm365_object_id',
        'm365_supervisor_object_id',
        'prefer_m365_supervisor',
        'department',
        'job_title',
        'phone',
        'employee_id',
        'is_active',
        'email_notifications_enabled',
        'custom_fields',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'prefer_m365_supervisor' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function effectiveSupervisor(): ?User
    {
        if ($this->prefer_m365_supervisor && $this->m365_supervisor_object_id) {
            return self::where('m365_object_id', $this->m365_supervisor_object_id)->first();
        }

        return $this->supervisor;
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret_enc) && $this->two_factor_confirmed_at !== null;
    }

    public function getTwoFactorSecret(): ?string
    {
        if (empty($this->two_factor_secret_enc)) return null;
        try {
            return Crypt::decryptString($this->two_factor_secret_enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setTwoFactorSecret(?string $secret): void
    {
        $this->two_factor_secret_enc = $secret === null ? null : Crypt::encryptString($secret);
    }

    /** @return array<int,string>|null */
    public function getTwoFactorRecoveryCodes(): ?array
    {
        if (empty($this->two_factor_recovery_codes_enc)) return null;
        try {
            $json = Crypt::decryptString($this->two_factor_recovery_codes_enc);
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<int,string>|null $codes */
    public function setTwoFactorRecoveryCodes(?array $codes): void
    {
        $this->two_factor_recovery_codes_enc = $codes === null
            ? null
            : Crypt::encryptString(json_encode(array_values($codes)));
    }
}
