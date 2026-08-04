<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;

final class EconomicClassEligibilityProjection
{
    public function code(Account $account): string
    {
        $code = DB::table('user_subscriptions')
            ->join('economic_classes', 'economic_classes.id', '=', 'user_subscriptions.economic_class_id')
            ->join('economic_class_versions', 'economic_class_versions.economic_class_id', '=', 'economic_classes.id')
            ->where('user_subscriptions.account_id', $account->id)
            ->where('user_subscriptions.status', 'active')
            ->where('economic_classes.status', 'active')
            ->where('economic_class_versions.state', ConfigurationState::Published->value)
            ->where('user_subscriptions.starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('user_subscriptions.ends_at')
                    ->orWhere('user_subscriptions.ends_at', '>', now());
            })
            ->where(function ($query): void {
                $query->whereNull('economic_class_versions.effective_from')
                    ->orWhere('economic_class_versions.effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('economic_class_versions.effective_to')
                    ->orWhere('economic_class_versions.effective_to', '>', now());
            })
            ->orderByDesc('user_subscriptions.starts_at')
            ->orderByDesc('economic_class_versions.version')
            ->value('economic_classes.code');

        return is_string($code) && $code !== '' ? $code : 'FREE';
    }
}
