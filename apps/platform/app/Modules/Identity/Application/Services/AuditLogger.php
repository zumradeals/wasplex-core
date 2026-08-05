<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\AccountAuditEvent;
use Illuminate\Http\Request;

final class AuditLogger
{
    /**
     * @param  array{
     *     actor_account_id?: ?string,
     *     actor_space_id?: ?string,
     *     organization_id?: ?string,
     *     capability_code?: ?string,
     *     resource_type?: ?string,
     *     resource_id?: ?string,
     *     reason?: ?string,
     *     session_id?: ?string,
     *     device_id?: ?string,
     * }  $context
     */
    public function record(string $action, array $context = [], ?Request $request = null): AccountAuditEvent
    {
        return AccountAuditEvent::create([
            ...$context,
            'action' => $action,
            'trace_id' => $request?->attributes->get('trace_id'),
        ]);
    }
}
