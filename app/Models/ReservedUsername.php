<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservedUsername extends Model
{
    protected $fillable = ['username', 'reason'];
}
