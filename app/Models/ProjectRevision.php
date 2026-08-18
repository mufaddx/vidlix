<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRevision extends Model
{
    protected $fillable = [
        'project_id', 'version_number', 'feedback', 'project_file_id',
        'submitted_by', 'submitted_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
