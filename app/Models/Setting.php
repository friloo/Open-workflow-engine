<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'system_settings';
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public const CREATED_AT = null;

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = [
        'value' => 'array',
        'updated_at' => 'datetime',
    ];
}
