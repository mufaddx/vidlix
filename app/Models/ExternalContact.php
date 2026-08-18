<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalContact extends Model
{
    protected $fillable = ['email', 'name', 'company'];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
