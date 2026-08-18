<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalVersion extends Model
{
    protected $fillable = [
        'proposal_id', 'version_number', 'amount_minor', 'currency', 'deliverables',
        'deadline', 'revisions', 'usage_rights', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deliverables' => 'array',
            'deadline' => 'date',
        ];
    }
}
