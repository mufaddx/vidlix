<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\RequestId;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(string $action, ?Model $auditable = null, array $meta = [], ?int $actorId = null, ?int $actingForCreatorId = null): AuditLog
    {
        return AuditLog::query()->create([
            'request_id' => RequestId::get(),
            'actor_user_id' => $actorId ?? auth()->id(),
            'acting_for_creator_id' => $actingForCreatorId,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'meta' => $meta,
            'ip_address' => request()->ip(),
        ]);
    }
}
