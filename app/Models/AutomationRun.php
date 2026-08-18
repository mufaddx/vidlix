<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $fillable = ['automation_id', 'status', 'detail'];
}
