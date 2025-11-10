<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
    ];

    public function balance(): HasOne {
        return $this->hasOne(Balance::class);
    }

    public function transaction(): HasMany {
        return $this->hasMany(Transaction::class);
    }
}
