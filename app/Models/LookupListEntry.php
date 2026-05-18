<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookupListEntry extends Model
{
    protected $fillable = ['lookup_list_id', 'key_value', 'data'];
    protected $casts = ['data' => 'array'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(LookupList::class, 'lookup_list_id');
    }
}
