<?php

namespace App\Services\ControlPlane;

use App\Models\ControlPlane\ControlAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function record(string $action, ?Model $target = null, ?array $before = null, ?array $after = null): void
    {
        $request = app()->bound('request') ? app(Request::class) : null;

        ControlAuditLog::query()->create([
            'actor_id' => auth('control')->id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'request_id' => $request?->attributes->get('request_id'),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
