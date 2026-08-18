<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'title', 'description', 'url', 'storage_key', 'sort_order'];
}
