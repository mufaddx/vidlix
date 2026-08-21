<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Somebody saved for later. Personal, and outlives any one campaign. */
class Favorite extends Model
{
    public const TYPES = ['creator', 'editor'];

    protected $fillable = ['user_id', 'subject_type', 'subject_id'];
}
