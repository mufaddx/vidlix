<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Project|null $project
 */
class ProjectFile extends Model
{
    protected $fillable = [
        'project_id', 'uploaded_by', 'kind', 'original_name', 'storage_key', 'disk',
        'mime', 'size_bytes', 'watermarked',
    ];

    protected function casts(): array
    {
        return ['watermarked' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
