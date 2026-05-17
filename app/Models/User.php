<?php

namespace App\Models;

use App\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'prefer_m365_supervisor' => 'boolean',
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
}
