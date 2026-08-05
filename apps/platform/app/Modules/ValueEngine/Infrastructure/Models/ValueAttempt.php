<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $value_event_definition_id
 * @property string $value_rule_version_id
 * @property string $account_id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property string $advertising_match_id
 * @property string $user_wallet_id
 * @property string $advertiser_wallet_id
 * @property string $campaign_funding_id
 * @property string $source_event_id
 * @property string $idempotency_key
 * @property ValueAttemptStatus $status
 * @property string $economic_class
 * @property string $country_code
 * @property string $unit
 * @property string $currency
 * @property int $gross_amount_minor
 * @property int $user_amount_minor
 * @property int $wasplex_amount_minor
 * @property string $calculation_hash
 * @property CarbonImmutable $reserved_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $proof_validated_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $released_at
 * @property string|null $release_reason
 * @property string|null $ledger_transaction_id
 * @property array<string, mixed>|null $metadata
 * @property-read ValueEventDefinition $eventDefinition
 * @property-read ValueRuleVersion $rule
 * @property-read Account $account
 * @property-read Campaign $campaign
 * @property-read CampaignVersion $campaignVersion
 * @property-read AdvertisingMatch $advertisingMatch
 * @property-read Wallet $userWallet
 * @property-read Wallet $advertiserWallet
 * @property-read CampaignFunding $campaignFunding
 * @property-read AttentionSession|null $attentionSession
 * @property-read LedgerTransaction|null $ledgerTransaction
 */
final class ValueAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'value_event_definition_id',
        'value_rule_version_id',
        'account_id',
        'campaign_id',
        'campaign_version_id',
        'advertising_match_id',
        'user_wallet_id',
        'advertiser_wallet_id',
        'campaign_funding_id',
        'source_event_id',
        'idempotency_key',
        'status',
        'economic_class',
        'country_code',
        'unit',
        'currency',
        'gross_amount_minor',
        'user_amount_minor',
        'wasplex_amount_minor',
        'calculation_hash',
        'reserved_at',
        'expires_at',
        'proof_validated_at',
        'settled_at',
        'released_at',
        'release_reason',
        'ledger_transaction_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ValueAttemptStatus::class,
            'gross_amount_minor' => 'integer',
            'user_amount_minor' => 'integer',
            'wasplex_amount_minor' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'proof_validated_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ValueEventDefinition, $this> */
    public function eventDefinition(): BelongsTo
    {
        return $this->belongsTo(ValueEventDefinition::class, 'value_event_definition_id');
    }

    /** @return BelongsTo<ValueRuleVersion, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ValueRuleVersion::class, 'value_rule_version_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<CampaignVersion, $this> */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class);
    }

    /** @return BelongsTo<AdvertisingMatch, $this> */
    public function advertisingMatch(): BelongsTo
    {
        return $this->belongsTo(AdvertisingMatch::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function userWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'user_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function advertiserWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'advertiser_wallet_id');
    }

    /** @return BelongsTo<CampaignFunding, $this> */
    public function campaignFunding(): BelongsTo
    {
        return $this->belongsTo(CampaignFunding::class);
    }

    /** @return HasOne<AttentionSession, $this> */
    public function attentionSession(): HasOne
    {
        return $this->hasOne(AttentionSession::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
