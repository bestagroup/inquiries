<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function write(string $event, ?Model $subject = null, array $metadata = [], ?Request $request = null): void
    {
        $request ??= request();

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
