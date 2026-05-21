<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractType extends Model
{
    protected $fillable = [
        'name', 'slug', 'color', 'default_notice_period_days', 'description', 'created_by',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'contract_type_role')
            ->withPivot('can_manage')
            ->withTimestamps();
    }
}
