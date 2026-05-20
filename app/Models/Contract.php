<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'party', 'category', 'description',
        'contract_type_id',
        'start_date', 'end_date', 'notice_period_days',
        'auto_renew', 'auto_renew_months',
        'status', 'owner_user_id', 'attachment_id',
        'last_reminder_at', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'notice_period_days' => 'integer',
        'auto_renew' => 'boolean',
        'auto_renew_months' => 'integer',
        'last_reminder_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /**
     * Mehrere Dateien koennen am Vertrag haengen (Vertrags-PDF, Anlagen,
     * AGB, Schriftverkehr). Aktuelle Versionen oben.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->where('is_current_version', true)
            ->latest();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'contract_role')
            ->withPivot('can_manage')
            ->withTimestamps();
    }

    /**
     * Sichtbarkeits-Scope: Vertraege die der gegebene User sehen darf.
     *
     * Sichtbar wenn:
     * - User ist admin (alles)
     * - User ist Owner des Vertrages
     * - User hat eine Rolle, die fuer den Vertragstyp freigegeben ist
     * - User hat eine Rolle, die direkt am Vertrag angeheftet ist
     * - Wenn kein contract_type_id und keine roles angeheftet sind:
     *   sichtbar (Legacy / unklassifiziert) — gleiches Pattern wie bei
     *   Dokumenten ohne document_type
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasRole('admin')) return $q;

        $roleIds = $user->roles->pluck('id')->all();

        return $q->where(function (Builder $q) use ($user, $roleIds) {
            $q->where('owner_user_id', $user->id)
              ->orWhereNull('contract_type_id') // unklassifiziert -> sichtbar
              ->orWhereIn('contract_type_id', function ($sub) use ($roleIds) {
                  $sub->select('contract_type_id')
                      ->from('contract_type_role')
                      ->whereIn('role_id', $roleIds);
              })
              ->orWhereIn('id', function ($sub) use ($roleIds) {
                  $sub->select('contract_id')
                      ->from('contract_role')
                      ->whereIn('role_id', $roleIds);
              });
        });
    }

    /**
     * Darf der gegebene User diesen Vertrag bearbeiten/loeschen?
     * Anders als Sichtbarkeit setzt das zusaetzlich die globale
     * contracts.manage-Permission voraus.
     */
    public function userCanManage(User $user): bool
    {
        if ($user->hasRole('admin')) return true;
        if (! $user->hasPermission('contracts.manage')) return false;
        if ($this->owner_user_id === $user->id) return true;

        $roleIds = $user->roles->pluck('id');

        // Per-Type: hat eine seiner Rollen can_manage am Typ?
        if ($this->contract_type_id) {
            $hasType = \DB::table('contract_type_role')
                ->where('contract_type_id', $this->contract_type_id)
                ->whereIn('role_id', $roleIds)
                ->where('can_manage', true)
                ->exists();
            if ($hasType) return true;
        }
        // Per-Contract: hat eine seiner Rollen can_manage hier?
        return \DB::table('contract_role')
            ->where('contract_id', $this->id)
            ->whereIn('role_id', $roleIds)
            ->where('can_manage', true)
            ->exists();
    }

    /**
     * Letzter Termin zu dem rechtzeitig gekuendigt werden muss
     * (end_date - notice_period_days). Null wenn end_date nicht gesetzt.
     */
    public function noticeDeadline(): ?\Illuminate\Support\Carbon
    {
        if (! $this->end_date) return null;
        return $this->end_date->copy()->subDays($this->notice_period_days);
    }

    /**
     * Status-Logik: berechnet, NICHT in DB schreiben. Cron synct das taeglich.
     */
    public function computedStatus(): string
    {
        if (! $this->end_date) return 'active';
        if ($this->end_date->isPast()) return 'expired';
        $deadline = $this->noticeDeadline();
        if ($deadline && $deadline->isPast()) return 'notice_due';
        return 'active';
    }
}
