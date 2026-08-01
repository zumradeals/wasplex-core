<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\AccessAuditEvent;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\Request;

final class AccessAuditor
{
    /** @param array<string, mixed> $context */
    public function record(
        Request $request,
        string $action,
        string $outcome,
        ?string $capability = null,
        array $context = [],
    ): AccessAuditEvent {
        $identitySession = $request->attributes->get('identity_session');
        $activeSpace = $request->attributes->get('active_space');

        return AccessAuditEvent::query()->create([
            'actor_account_id' => $request->user()?->getAuthIdentifier(),
            'actor_space_id' => $identitySession instanceof AccountSession ? $identitySession->active_space_id : null,
            'organization_id' => $activeSpace instanceof UserSpace ? $activeSpace->organization_id : null,
            'session_id' => $identitySession instanceof AccountSession ? $identitySession->id : null,
            'device_id' => $identitySession instanceof AccountSession ? $identitySession->device_id : null,
            'capability' => $capability,
            'action' => $action,
            'outcome' => $outcome,
            'trace_id' => $request->attributes->get('trace_id'),
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }
}
