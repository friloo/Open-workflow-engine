<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'party', 'category', 'description',
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
