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
        'delegate_user_id',
        'delegate_from',
        'delegate_to',
        'delegate_reason',
        'm365_object_id',
        'm365_supervisor_object_id',
        'prefer_m365_supervisor',
        'oidc_subject',
        'google_subject',
        'saml_nameid',
        'ldap_dn',
        'ical_token',
        'is_service_account',
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

    /**
     * Scope: nur menschliche Benutzer (keine Service-Accounts).
     * Verwendet in allen User-Auswahllisten — Service-Accounts sollen
     * nicht als Approver, Supervisor o.ae. ausgewaehlt werden.
     */
    public function scopeHumans($query)
    {
        return $query->where(function ($q) {
            $q->where('is_service_account', false)->orWhereNull('is_service_account');
        });
    }

    public function isServiceAccount(): bool
    {
        return (bool) $this->is_service_account;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'delegate_from' => 'date',
            'delegate_to' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_service_account' => 'boolean',
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

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'delegate_user_id');
    }

    /**
     * Liefert die aktive Vertretung — oder null, wenn aktuell keine
     * gilt. Wenn der Vertreter selbst ebenfalls vertreten ist,
     * folgen wir der Kette (max. 3 Hops, um Zyklen zu kappen).
     */
    public function activeDelegate(?\DateTimeInterface $on = null): ?User
    {
        $on = $on ? \Carbon\Carbon::instance($on)->startOfDay() : now()->startOfDay();
        $current = $this;
        for ($i = 0; $i < 3; $i++) {
            if (! $current->delegate_user_id) return $current === $this ? null : $current;
            if (! $current->delegate_from || ! $current->delegate_to) return $current === $this ? null : $current;
            if ($on->lt($current->delegate_from) || $on->gt($current->delegate_to)) {
                return $current === $this ? null : $current;
            }
            $next = self::find($current->delegate_user_id);
            if (! $next || ! $next->is_active || $next->id === $this->id) {
                return $current === $this ? null : $current;
            }
            $current = $next;
        }
        return $current === $this ? null : $current;
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
