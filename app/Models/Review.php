<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'reviewer_user_id', 'reviewee_user_id', 'reviewable_type', 'reviewable_id', 'rating', 'body',
    ];
}
