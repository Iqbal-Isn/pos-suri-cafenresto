<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public function record(
        ?int $outletId,
        ?int $userId,
        ?int $authorizedBy,
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?string $reason = null,
        ?array $payload = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'outlet_id' => $outletId,
            'user_id' => $userId,
            'authorized_by' => $authorizedBy,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'reason' => $reason,
            'payload' => $payload,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
