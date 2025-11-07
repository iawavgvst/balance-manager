<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'comment',
        'related_user_id'
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
