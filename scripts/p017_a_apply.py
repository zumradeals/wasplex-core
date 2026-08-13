from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLATFORM = ROOT / 'apps/platform'

files = {
    'apps/platform/app/Modules/Card/Database/Migrations/2026_08_13_120000_create_card_foundation.php': r'''<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('card_offer_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('offer_id')->constrained('card_offers')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('supports_virtual')->default(true);
            $table->boolean('supports_physical')->default(false);
            $table->json('services')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['offer_id', 'version']);
        });

        Schema::create('cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('offer_version_id')->constrained('card_offer_versions')->restrictOnDelete();
            $table->string('public_identifier')->unique();
            $table->string('status')->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('card_qr_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'status', 'expires_at']);
        });

        Schema::create('card_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'event_type']);
        });

        $offerId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $now = now();

        DB::table('card_offers')->insert([
            'id' => $offerId,
            'code' => 'WASPLEX_BASE',
            'name' => 'Wasplex Base',
            'description' => 'Carte virtuelle Wasplex de base, liée au compte et à son Wallet.',
            'status' => 'active',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('card_offer_versions')->insert([
            'id' => $versionId,
            'offer_id' => $offerId,
            'version' => 1,
            'status' => 'published',
            'price_minor' => 0,
            'currency' => 'XOF',
            'duration_days' => null,
            'supports_virtual' => true,
            'supports_physical' => false,
            'services' => json_encode(['identity', 'qr_public', 'wallet_entry']),
            'effective_from' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('card_audit_events');
        Schema::dropIfExists('card_qr_tokens');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('card_offer_versions');
        Schema::dropIfExists('card_offers');
    }
};
''',
    'apps/platform/app/Modules/Card/Infrastructure/Models/CardOffer.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CardOffer extends Model
{
    use HasUlids;

    protected $fillable = ['code', 'name', 'description', 'status', 'sort_order'];

    public function versions(): HasMany
    {
        return $this->hasMany(CardOfferVersion::class, 'offer_id');
    }
}
''',
    'apps/platform/app/Modules/Card/Infrastructure/Models/CardOfferVersion.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardOfferVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'offer_id', 'version', 'status', 'price_minor', 'currency', 'duration_days',
        'supports_virtual', 'supports_physical', 'services', 'effective_from', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'duration_days' => 'integer',
            'supports_virtual' => 'boolean',
            'supports_physical' => 'boolean',
            'services' => 'array',
            'effective_from' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CardOffer::class, 'offer_id');
    }
}
''',
    'apps/platform/app/Modules/Card/Infrastructure/Models/Card.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Card extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'account_id', 'offer_version_id', 'public_identifier', 'status', 'issued_at',
        'activated_at', 'expires_at', 'suspended_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function offerVersion(): BelongsTo
    {
        return $this->belongsTo(CardOfferVersion::class, 'offer_version_id');
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(CardQrToken::class, 'card_id');
    }
}
''',
    'apps/platform/app/Modules/Card/Infrastructure/Models/CardQrToken.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardQrToken extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = ['card_id', 'purpose', 'token_hash', 'status', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }
}
''',
    'apps/platform/app/Modules/Card/Infrastructure/Models/CardAuditEvent.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class CardAuditEvent extends Model
{
    use HasUlids;

    protected $fillable = ['card_id', 'account_id', 'event_type', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
''',
    'apps/platform/app/Modules/Card/Application/Services/CardService.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardAuditEvent;
use App\Modules\Card\Infrastructure\Models\CardOfferVersion;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CardService
{
    public function overview(Account $account): array
    {
        $card = Card::query()
            ->with('offerVersion.offer')
            ->where('account_id', $account->id)
            ->whereNotIn('status', [Card::STATUS_CLOSED])
            ->latest('issued_at')
            ->first();

        $baseVersion = $this->baseVersion();

        return [
            'card' => $card === null ? null : $this->cardPayload($card),
            'offer' => [
                'code' => $baseVersion->offer->code,
                'name' => $baseVersion->offer->name,
                'description' => $baseVersion->offer->description,
                'price_minor' => $baseVersion->price_minor,
                'currency' => $baseVersion->currency,
                'supports_virtual' => $baseVersion->supports_virtual,
                'supports_physical' => $baseVersion->supports_physical,
            ],
        ];
    }

    public function issueBase(Account $account): Card
    {
        return DB::transaction(function () use ($account): Card {
            Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $existing = Card::query()
                ->with('offerVersion.offer')
                ->where('account_id', $account->id)
                ->whereNotIn('status', [Card::STATUS_CLOSED])
                ->latest('issued_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $version = $this->baseVersion();
            $card = Card::query()->create([
                'account_id' => $account->id,
                'offer_version_id' => $version->id,
                'public_identifier' => $this->newPublicIdentifier($account->country_code),
                'status' => Card::STATUS_ACTIVE,
                'issued_at' => now(),
                'activated_at' => now(),
                'expires_at' => $version->duration_days === null ? null : now()->addDays($version->duration_days),
            ]);

            $this->audit($card, $account->id, 'CardIssued', ['offer_code' => $version->offer->code]);

            return $card->load('offerVersion.offer');
        });
    }

    public function generateIdentityQr(Card $card, string $accountId): array
    {
        $this->assertOwner($card, $accountId);
        if ($card->status !== Card::STATUS_ACTIVE) {
            throw new ConflictHttpException('La Carte Wasplex doit être active pour générer un QR.');
        }

        return DB::transaction(function () use ($card, $accountId): array {
            CardQrToken::query()
                ->where('card_id', $card->id)
                ->where('purpose', 'public_identity')
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);

            $secret = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $token = CardQrToken::query()->create([
                'card_id' => $card->id,
                'purpose' => 'public_identity',
                'token_hash' => hash('sha256', $secret),
                'status' => CardQrToken::STATUS_ACTIVE,
                'expires_at' => now()->addMinutes(2),
            ]);

            $payload = url('/api/card-scan/resolve').'?token='.rawurlencode($secret);
            $this->audit($card, $accountId, 'CardQrGenerated', [
                'token_id' => $token->id,
                'purpose' => $token->purpose,
                'expires_at' => $token->expires_at?->toIso8601String(),
            ]);

            return [
                'token_id' => $token->id,
                'purpose' => $token->purpose,
                'payload' => $payload,
                'expires_at' => $token->expires_at?->toIso8601String(),
            ];
        });
    }

    public function resolvePublicIdentity(string $secret): array
    {
        return DB::transaction(function () use ($secret): array {
            $token = CardQrToken::query()
                ->with('card.account.profile', 'card.offerVersion.offer')
                ->where('token_hash', hash('sha256', $secret))
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                throw new NotFoundHttpException('QR Carte Wasplex introuvable.');
            }
            if ($token->status !== CardQrToken::STATUS_ACTIVE || $token->used_at !== null) {
                throw new GoneHttpException('Ce QR Carte Wasplex a déjà été utilisé.');
            }
            if ($token->expires_at->isPast()) {
                $token->update(['status' => CardQrToken::STATUS_REVOKED]);
                throw new GoneHttpException('Ce QR Carte Wasplex a expiré.');
            }

            $card = $token->card;
            if ($card->status !== Card::STATUS_ACTIVE) {
                throw new GoneHttpException('Cette Carte Wasplex n’est pas active.');
            }

            $token->update(['status' => CardQrToken::STATUS_USED, 'used_at' => now()]);
            $this->audit($card, $card->account_id, 'CardQrResolved', ['token_id' => $token->id]);

            return [
                'valid' => true,
                'card' => [
                    'public_identifier' => $card->public_identifier,
                    'status' => $card->status,
                    'display_name' => $card->account->profile?->display_name ?: 'Membre Wasplex',
                    'offer_name' => $card->offerVersion->offer->name,
                    'verified' => $card->account->verified_at !== null,
                ],
            ];
        });
    }

    public function suspend(Card $card, string $accountId): Card
    {
        $this->assertOwner($card, $accountId);

        return DB::transaction(function () use ($card, $accountId): Card {
            $locked = Card::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Card::STATUS_SUSPENDED) {
                return $locked->load('offerVersion.offer');
            }
            if ($locked->status !== Card::STATUS_ACTIVE) {
                throw new ConflictHttpException('Cette Carte Wasplex ne peut pas être suspendue dans son état actuel.');
            }

            $locked->update(['status' => Card::STATUS_SUSPENDED, 'suspended_at' => now()]);
            CardQrToken::query()
                ->where('card_id', $locked->id)
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);
            $this->audit($locked, $accountId, 'CardSuspended');

            return $locked->refresh()->load('offerVersion.offer');
        });
    }

    public function cardPayload(Card $card): array
    {
        $card->loadMissing('offerVersion.offer', 'account.profile');

        return [
            'id' => $card->id,
            'public_identifier' => $card->public_identifier,
            'status' => $card->status,
            'display_name' => $card->account->profile?->display_name ?: 'Membre Wasplex',
            'offer' => [
                'code' => $card->offerVersion->offer->code,
                'name' => $card->offerVersion->offer->name,
            ],
            'issued_at' => $card->issued_at?->toIso8601String(),
            'activated_at' => $card->activated_at?->toIso8601String(),
            'expires_at' => $card->expires_at?->toIso8601String(),
            'supports_virtual' => (bool) $card->offerVersion->supports_virtual,
            'supports_physical' => (bool) $card->offerVersion->supports_physical,
        ];
    }

    private function baseVersion(): CardOfferVersion
    {
        return CardOfferVersion::query()
            ->with('offer')
            ->where('status', 'published')
            ->whereHas('offer', fn ($query) => $query->where('code', 'WASPLEX_BASE')->where('status', 'active'))
            ->orderByDesc('version')
            ->firstOrFail();
    }

    private function newPublicIdentifier(string $countryCode): string
    {
        do {
            $random = strtoupper(Str::random(6));
            $identifier = 'WPLX-'.strtoupper($countryCode).'-'.substr($random, 0, 4).'-'.substr($random, 4, 2);
        } while (Card::query()->where('public_identifier', $identifier)->exists());

        return $identifier;
    }

    private function assertOwner(Card $card, string $accountId): void
    {
        if ($card->account_id !== $accountId) {
            throw new NotFoundHttpException('Carte Wasplex introuvable.');
        }
    }

    private function audit(Card $card, ?string $accountId, string $eventType, array $metadata = []): void
    {
        CardAuditEvent::query()->create([
            'card_id' => $card->id,
            'account_id' => $accountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
''',
    'apps/platform/app/Modules/Card/Http/Controllers/CardsController.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardService;
use App\Modules\Card\Infrastructure\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardsController
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cards->overview($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $card = $this->cards->issueBase($request->user());

        return response()->json(['card' => $this->cards->cardPayload($card)], 201);
    }

    public function qr(Request $request, Card $card): JsonResponse
    {
        return response()->json(['qr' => $this->cards->generateIdentityQr($card, (string) $request->user()->id)]);
    }

    public function suspend(Request $request, Card $card): JsonResponse
    {
        $card = $this->cards->suspend($card, (string) $request->user()->id);

        return response()->json(['card' => $this->cards->cardPayload($card)]);
    }
}
''',
    'apps/platform/app/Modules/Card/Http/Controllers/CardScanController.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardScanController
{
    public function __construct(private readonly CardService $cards) {}

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:120']]);

        return response()->json($this->cards->resolvePublicIdentity($data['token']));
    }
}
''',
    'apps/platform/app/Modules/Card/Http/routes/api.php': r'''<?php

declare(strict_types=1);

use App\Modules\Card\Http\Controllers\CardsController;
use App\Modules\Card\Http\Controllers\CardScanController;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('cards')
    ->group(function (): void {
        Route::get('/', [CardsController::class, 'index']);
        Route::post('/', [CardsController::class, 'store']);
        Route::post('/{card}/qr', [CardsController::class, 'qr']);
        Route::post('/{card}/suspend', [CardsController::class, 'suspend']);
    });

Route::match(['get', 'post'], 'card-scan/resolve', [CardScanController::class, 'resolve'])
    ->middleware('throttle:60,1');
''',
    'apps/platform/app/Modules/Card/Infrastructure/Providers/CardServiceProvider.php': r'''<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
''',
    'apps/platform/resources/js/lib/qrCode.ts': r'''type Module = boolean | null;

const VERSION = 6;
const SIZE = 17 + VERSION * 4;
const DATA_CODEWORDS = 136;
const BLOCK_COUNT = 2;
const DATA_PER_BLOCK = 68;
const ECC_PER_BLOCK = 18;
const REMAINDER_BITS = 7;
const GF_EXP = new Array<number>(512).fill(0);
const GF_LOG = new Array<number>(256).fill(0);

let x = 1;
for (let i = 0; i < 255; i += 1) {
    GF_EXP[i] = x;
    GF_LOG[x] = i;
    x <<= 1;
    if ((x & 0x100) !== 0) x ^= 0x11d;
}
for (let i = 255; i < 512; i += 1) GF_EXP[i] = GF_EXP[i - 255];

function gfMul(a: number, b: number): number {
    if (a === 0 || b === 0) return 0;
    return GF_EXP[GF_LOG[a] + GF_LOG[b]];
}

function generator(degree: number): number[] {
    let result = [1];
    for (let i = 0; i < degree; i += 1) {
        const next = new Array<number>(result.length + 1).fill(0);
        for (let j = 0; j < result.length; j += 1) {
            next[j] ^= result[j];
            next[j + 1] ^= gfMul(result[j], GF_EXP[i]);
        }
        result = next;
    }
    return result;
}

function ecc(data: number[]): number[] {
    const gen = generator(ECC_PER_BLOCK);
    const remainder = new Array<number>(ECC_PER_BLOCK).fill(0);
    for (const value of data) {
        const factor = value ^ remainder[0];
        remainder.shift();
        remainder.push(0);
        for (let i = 0; i < ECC_PER_BLOCK; i += 1) remainder[i] ^= gfMul(gen[i + 1], factor);
    }
    return remainder;
}

function appendBits(target: number[], value: number, count: number): void {
    for (let i = count - 1; i >= 0; i -= 1) target.push((value >>> i) & 1);
}

function encodePayload(payload: string): number[] {
    const bytes = Array.from(new TextEncoder().encode(payload));
    if (bytes.length > 134) throw new Error('QR payload too long for Wasplex Card QR v1.');

    const bits: number[] = [];
    appendBits(bits, 0b0100, 4);
    appendBits(bits, bytes.length, 8);
    for (const byte of bytes) appendBits(bits, byte, 8);

    const capacity = DATA_CODEWORDS * 8;
    for (let i = 0; i < Math.min(4, capacity - bits.length); i += 1) bits.push(0);
    while (bits.length % 8 !== 0) bits.push(0);

    const data: number[] = [];
    for (let i = 0; i < bits.length; i += 8) {
        let value = 0;
        for (let j = 0; j < 8; j += 1) value = (value << 1) | bits[i + j];
        data.push(value);
    }
    let pad = true;
    while (data.length < DATA_CODEWORDS) {
        data.push(pad ? 0xec : 0x11);
        pad = !pad;
    }

    const blocks = new Array<number[]>(BLOCK_COUNT);
    const eccBlocks = new Array<number[]>(BLOCK_COUNT);
    for (let block = 0; block < BLOCK_COUNT; block += 1) {
        blocks[block] = data.slice(block * DATA_PER_BLOCK, (block + 1) * DATA_PER_BLOCK);
        eccBlocks[block] = ecc(blocks[block]);
    }

    const codewords: number[] = [];
    for (let i = 0; i < DATA_PER_BLOCK; i += 1) {
        for (let block = 0; block < BLOCK_COUNT; block += 1) codewords.push(blocks[block][i]);
    }
    for (let i = 0; i < ECC_PER_BLOCK; i += 1) {
        for (let block = 0; block < BLOCK_COUNT; block += 1) codewords.push(eccBlocks[block][i]);
    }

    const result: number[] = [];
    for (const word of codewords) appendBits(result, word, 8);
    for (let i = 0; i < REMAINDER_BITS; i += 1) result.push(0);
    return result;
}

function bchDigit(value: number): number {
    let digit = 0;
    while (value !== 0) {
        digit += 1;
        value >>>= 1;
    }
    return digit;
}

function typeInfoBits(data: number): number {
    const generator = 0x537;
    let value = data << 10;
    while (bchDigit(value) - bchDigit(generator) >= 0) {
        value ^= generator << (bchDigit(value) - bchDigit(generator));
    }
    return ((data << 10) | value) ^ 0x5412;
}

function finder(matrix: Module[][], row: number, col: number): void {
    for (let r = -1; r <= 7; r += 1) {
        for (let c = -1; c <= 7; c += 1) {
            const rr = row + r;
            const cc = col + c;
            if (rr < 0 || rr >= SIZE || cc < 0 || cc >= SIZE) continue;
            const inside = r >= 0 && r <= 6 && c >= 0 && c <= 6;
            matrix[rr][cc] = inside && (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4));
        }
    }
}

function alignment(matrix: Module[][], row: number, col: number): void {
    if (matrix[row][col] !== null) return;
    for (let r = -2; r <= 2; r += 1) {
        for (let c = -2; c <= 2; c += 1) {
            matrix[row + r][col + c] = Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0);
        }
    }
}

function formatInfo(matrix: Module[][]): void {
    const bits = typeInfoBits(0b01000); // Error correction L, mask 0.
    for (let i = 0; i < 15; i += 1) {
        const value = ((bits >> i) & 1) === 1;
        if (i < 6) matrix[i][8] = value;
        else if (i < 8) matrix[i + 1][8] = value;
        else matrix[SIZE - 15 + i][8] = value;

        if (i < 8) matrix[8][SIZE - i - 1] = value;
        else if (i < 9) matrix[8][15 - i] = value;
        else matrix[8][15 - i - 1] = value;
    }
    matrix[SIZE - 8][8] = true;
}

export function makeQrMatrix(payload: string): boolean[][] {
    const matrix: Module[][] = Array.from({ length: SIZE }, () => new Array<Module>(SIZE).fill(null));
    finder(matrix, 0, 0);
    finder(matrix, SIZE - 7, 0);
    finder(matrix, 0, SIZE - 7);

    for (let i = 8; i < SIZE - 8; i += 1) {
        if (matrix[6][i] === null) matrix[6][i] = i % 2 === 0;
        if (matrix[i][6] === null) matrix[i][6] = i % 2 === 0;
    }
    alignment(matrix, 34, 34);
    formatInfo(matrix);

    const bits = encodePayload(payload);
    let bitIndex = 0;
    let row = SIZE - 1;
    let direction = -1;

    for (let col = SIZE - 1; col > 0; col -= 2) {
        if (col === 6) col -= 1;
        while (true) {
            for (let offset = 0; offset < 2; offset += 1) {
                const cc = col - offset;
                if (matrix[row][cc] !== null) continue;
                let value = bitIndex < bits.length ? bits[bitIndex] === 1 : false;
                bitIndex += 1;
                if ((row + cc) % 2 === 0) value = !value;
                matrix[row][cc] = value;
            }
            row += direction;
            if (row < 0 || row >= SIZE) {
                row -= direction;
                direction = -direction;
                break;
            }
        }
    }

    return matrix.map((line) => line.map((cell) => cell === true));
}
''',
    'apps/platform/resources/js/Components/CardPanel.vue': r'''<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import http from '@/lib/http';
import { makeQrMatrix } from '@/lib/qrCode';

interface CardData {
    id: string;
    public_identifier: string;
    status: string;
    display_name: string;
    offer: { code: string; name: string };
    issued_at: string | null;
    expires_at: string | null;
    supports_virtual: boolean;
    supports_physical: boolean;
}

interface OfferData {
    code: string;
    name: string;
    description: string | null;
    price_minor: number;
    currency: string;
    supports_virtual: boolean;
    supports_physical: boolean;
}

interface QrData {
    token_id: string;
    purpose: string;
    payload: string;
    expires_at: string;
}

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

const loading = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const card = ref<CardData | null>(null);
const offer = ref<OfferData | null>(null);
const qr = ref<QrData | null>(null);

const statusLabel = computed(() => {
    if (!card.value) return '';
    if (card.value.status === 'active') return 'Active';
    if (card.value.status === 'suspended') return 'Suspendue';
    return card.value.status;
});

const qrMatrix = computed(() => (qr.value ? makeQrMatrix(qr.value.payload) : []));
const qrPath = computed(() => {
    const commands: string[] = [];
    qrMatrix.value.forEach((row, y) => row.forEach((cell, x) => {
        if (cell) commands.push(`M${x + 4} ${y + 4}h1v1h-1z`);
    }));
    return commands.join('');
});
const qrSize = computed(() => (qrMatrix.value.length || 41) + 8);

function messageFrom(errorValue: unknown): string {
    const response = (errorValue as { response?: { data?: { message?: string } } })?.response;
    return response?.data?.message ?? 'Une erreur est survenue. Réessayez.';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/cards');
        card.value = data.card;
        offer.value = data.offer;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        loading.value = false;
    }
}

async function issue(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/cards');
        card.value = data.card;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function generateQr(): Promise<void> {
    if (!card.value) return;
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/cards/${card.value.id}/qr`);
        qr.value = data.qr;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function suspend(): Promise<void> {
    if (!card.value || !window.confirm('Suspendre immédiatement cette Carte Wasplex ? Les QR actifs seront révoqués.')) return;
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/cards/${card.value.id}/suspend`);
        card.value = data.card;
        qr.value = null;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

watch(() => props.open, (open) => {
    if (open) void load();
    else qr.value = null;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex justify-center bg-black/70" @click.self="emit('close')">
            <div class="bg-wpx-navy-950 min-h-full w-full max-w-md overflow-y-auto px-4 py-5">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-gold text-xs font-bold tracking-[0.18em] uppercase">Carte Wasplex</p>
                        <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Ma Carte</h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Identité minimale, QR sécurisé et accès aux services.</p>
                    </div>
                    <button type="button" aria-label="Fermer" class="bg-wpx-navy-750 text-wpx-white-soft flex h-10 w-10 items-center justify-center rounded-full text-xl" @click="emit('close')">×</button>
                </div>

                <p v-if="error" role="alert" class="bg-wpx-danger/10 text-wpx-danger mb-4 rounded-xl px-3 py-2 text-xs">{{ error }}</p>
                <div v-if="loading" class="text-wpx-muted-dark py-16 text-center text-sm">Chargement de votre Carte…</div>

                <template v-else-if="card">
                    <section class="from-wpx-navy-700 via-wpx-navy-850 to-wpx-navy-950 border-wpx-gold/25 relative overflow-hidden rounded-[26px] border bg-gradient-to-br p-5 shadow-2xl">
                        <div class="bg-wpx-orange/15 absolute -top-12 -right-12 h-36 w-36 rounded-full blur-2xl"></div>
                        <div class="relative flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-8 w-8 object-contain" />
                                <div>
                                    <p class="text-wpx-white-soft text-sm font-extrabold">WASPLEX</p>
                                    <p class="text-wpx-muted-dark text-[9px] tracking-[0.18em] uppercase">Carte de services</p>
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold" :class="card.status === 'active' ? 'bg-wpx-success/15 text-wpx-success-light' : 'bg-wpx-gold/15 text-wpx-gold'">{{ statusLabel }}</span>
                        </div>

                        <div class="relative mt-10">
                            <p class="text-wpx-muted-dark text-[9px] tracking-[0.16em] uppercase">Titulaire</p>
                            <p class="text-wpx-white-soft mt-1 truncate text-lg font-bold">{{ card.display_name }}</p>
                            <p class="text-wpx-gold mt-4 font-mono text-sm tracking-[0.12em]">{{ card.public_identifier }}</p>
                        </div>

                        <div class="relative mt-7 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Offre</p>
                                <p class="text-wpx-white-soft mt-0.5 text-xs font-semibold">{{ card.offer.name }}</p>
                            </div>
                            <span class="text-wpx-muted-dark text-[10px]">Virtuelle</span>
                        </div>
                    </section>

                    <section class="mt-4 grid grid-cols-2 gap-2.5">
                        <button type="button" :disabled="busy || card.status !== 'active'" class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-3 text-sm font-extrabold disabled:opacity-40" @click="generateQr">Afficher mon QR</button>
                        <button type="button" class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft rounded-xl border px-3 py-3 text-sm font-bold" @click="emit('close')">Fermer</button>
                    </section>

                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-2xl border p-4">
                        <p class="text-wpx-white-soft text-sm font-bold">Protection de vos données</p>
                        <p class="text-wpx-muted-dark mt-1.5 text-xs leading-relaxed">Votre QR public ne révèle ni téléphone, ni e-mail, ni solde Wallet, ni KYC, ni données Santé ou Fonds.</p>
                    </section>

                    <button v-if="card.status === 'active'" type="button" :disabled="busy" class="text-wpx-danger mt-4 w-full py-2 text-xs font-bold disabled:opacity-40" @click="suspend">Suspendre ma Carte</button>
                    <p v-else class="text-wpx-gold mt-4 text-center text-xs">Cette carte est suspendue. Sa réactivation sera traitée dans le cycle de vie Carte.</p>
                </template>

                <section v-else class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-5 text-center">
                    <span class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br text-2xl">▣</span>
                    <h3 class="text-wpx-white-soft mt-4 text-base font-bold">Activez votre Carte Wasplex virtuelle</h3>
                    <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">{{ offer?.description ?? 'Une carte liée à votre compte, sans solde séparé du Wallet.' }}</p>
                    <p class="text-wpx-success-light mt-3 text-sm font-extrabold">Gratuite</p>
                    <button type="button" :disabled="busy" class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-40" @click="issue">{{ busy ? 'Activation…' : 'Créer ma carte virtuelle' }}</button>
                </section>
            </div>

            <div v-if="qr" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-5" @click.self="qr = null">
                <div class="w-full max-w-xs rounded-3xl bg-white p-5 text-center shadow-2xl">
                    <p class="text-sm font-extrabold text-slate-900">QR d’identité Wasplex</p>
                    <p class="mt-1 text-[11px] text-slate-500">Valable 2 minutes · usage unique</p>
                    <svg class="mx-auto mt-4 h-64 w-64" :viewBox="`0 0 ${qrSize} ${qrSize}`" role="img" aria-label="QR Carte Wasplex">
                        <rect :width="qrSize" :height="qrSize" fill="white" />
                        <path :d="qrPath" fill="black" />
                    </svg>
                    <p class="mt-2 font-mono text-[10px] text-slate-500">{{ card?.public_identifier }}</p>
                    <button type="button" class="bg-wpx-navy-950 mt-4 w-full rounded-xl px-4 py-2.5 text-sm font-bold text-white" @click="qr = null">Masquer le QR</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
''',
    'apps/platform/tests/Feature/Card/CardFoundationTest.php': r'''<?php

declare(strict_types=1);

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardAuditEvent;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;

it('issues one virtual Wasplex Base card per member without creating a card balance', function (): void {
    registerAndLogin('card-base@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-base@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $account->id)->update(['display_name' => 'Awa Wasplex']);

    $first = test()->postJson('/api/cards')->assertCreated();
    $cardId = $first->json('card.id');
    $first->assertJsonPath('card.offer.code', 'WASPLEX_BASE')
        ->assertJsonPath('card.status', Card::STATUS_ACTIVE)
        ->assertJsonPath('card.supports_virtual', true)
        ->assertJsonMissingPath('card.phone')
        ->assertJsonMissingPath('card.email')
        ->assertJsonMissingPath('card.balance');

    test()->postJson('/api/cards')->assertCreated()->assertJsonPath('card.id', $cardId);
    expect(Card::query()->where('account_id', $account->id)->count())->toBe(1);
    expect(CardAuditEvent::query()->where('event_type', 'CardIssued')->count())->toBe(1);
});

it('generates an expiring single-use QR and resolves only a minimal public identity', function (): void {
    registerAndLogin('card-qr@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-qr@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $account->id)->update(['display_name' => 'Moussa Test']);

    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $response = test()->postJson("/api/cards/{$cardId}/qr")->assertOk();
    $payload = $response->json('qr.payload');
    parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
    $secret = $query['token'] ?? null;
    expect($secret)->toBeString()->not->toBeEmpty();

    test()->getJson('/api/card-scan/resolve?token='.urlencode($secret))
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('card.display_name', 'Moussa Test')
        ->assertJsonPath('card.offer_name', 'Wasplex Base')
        ->assertJsonMissingPath('card.phone')
        ->assertJsonMissingPath('card.email')
        ->assertJsonMissingPath('card.balance');

    test()->getJson('/api/card-scan/resolve?token='.urlencode($secret))->assertGone();
    expect(CardAuditEvent::query()->where('event_type', 'CardQrResolved')->count())->toBe(1);
});

it('revokes active QR tokens when the owner suspends the card and blocks new QR generation', function (): void {
    registerAndLogin('card-suspend@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $qr = test()->postJson("/api/cards/{$cardId}/qr")->assertOk()->json('qr.payload');
    parse_str((string) parse_url($qr, PHP_URL_QUERY), $query);

    test()->postJson("/api/cards/{$cardId}/suspend")
        ->assertOk()
        ->assertJsonPath('card.status', Card::STATUS_SUSPENDED);

    test()->getJson('/api/card-scan/resolve?token='.urlencode($query['token']))->assertGone();
    test()->postJson("/api/cards/{$cardId}/qr")->assertConflict();
    expect(CardAuditEvent::query()->where('event_type', 'CardSuspended')->count())->toBe(1);
});

it('prevents another authenticated member from operating a card they do not own', function (): void {
    registerAndLogin('card-owner@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-intruder@example.com');

    test()->postJson("/api/cards/{$cardId}/qr")->assertNotFound();
    test()->postJson("/api/cards/{$cardId}/suspend")->assertNotFound();
});
''',
    'docs/chantiers/P017-A-CARTE-VIRTUELLE.md': r'''# P017-A — Carte Wasplex : fondation virtuelle

## Objectif

Livrer la première fondation réelle du module transversal Carte Wasplex, en conservant le Wallet comme source de vérité et le raccourci « Carte Wasplex » déjà présent dans Mon Espace comme porte d’entrée.

## Périmètre

- offre gratuite `Wasplex Base` versionnée ;
- émission idempotente d’une carte virtuelle personnelle ;
- identifiant public non sensible `WPLX-<PAYS>-XXXX-XX` ;
- écran « Ma Carte » dans Mon Espace ;
- QR public d’identité dynamique, expirant après 2 minutes et à usage unique ;
- résolution publique minimale : identifiant, nom d’affichage, offre, statut, badge de vérification ;
- anti-replay ;
- suspension immédiate par le titulaire ;
- révocation des QR actifs à la suspension ;
- audit des émissions, générations, résolutions et suspensions.

## Invariants

1. La Carte n’a aucun solde indépendant : le Wallet reste la source de vérité.
2. Un scan ne déclenche aucune opération financière.
3. Le QR ne transporte pas de téléphone, e-mail, solde, KYC, Santé ou Fonds.
4. Un jeton QR expiré, révoqué ou déjà utilisé est refusé.
5. Les actions privées vérifient systématiquement que la carte appartient au compte authentifié.
6. La Carte reste transversale et n’est pas ajoutée comme sixième onglet principal.

## Hors périmètre P017-A

- paiement Carte et QR de réception Wallet ;
- offres partenaires, cashback et règlements ;
- support physique ;
- Alertes par Carte ;
- Santé d’urgence par Carte ;
- administration complète des offres et supports ;
- renouvellement/remplacement/réactivation.

Ces éléments suivent dans les lots P017 suivants.
''',
}

for relative, content in files.items():
    path = ROOT / relative
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')

providers = PLATFORM / 'bootstrap/providers.php'
text = providers.read_text(encoding='utf-8')
text = text.replace(
    'use App\\Modules\\Campaigns\\Infrastructure\\Providers\\CampaignsServiceProvider;\n',
    'use App\\Modules\\Campaigns\\Infrastructure\\Providers\\CampaignsServiceProvider;\nuse App\\Modules\\Card\\Infrastructure\\Providers\\CardServiceProvider;\n',
)
text = text.replace(
    '    CampaignsServiceProvider::class,\n',
    '    CampaignsServiceProvider::class,\n    CardServiceProvider::class,\n',
)
providers.write_text(text, encoding='utf-8')

shell = PLATFORM / 'resources/js/Pages/Identity/UserShell.vue'
text = shell.read_text(encoding='utf-8')
text = text.replace("import { useComingSoon } from '@/lib/comingSoon';\n", "")
text = text.replace("import AccountSecurityPanel from '@/Components/AccountSecurityPanel.vue';\n", "import AccountSecurityPanel from '@/Components/AccountSecurityPanel.vue';\nimport CardPanel from '@/Components/CardPanel.vue';\n")
text = text.replace("const showAccountSecurity = ref(false);\n\nconst { notice: espaceNotice, announce: announceComingSoon } = useComingSoon();\n", "const showAccountSecurity = ref(false);\nconst showCard = ref(false);\n")
text = text.replace('@click="announceComingSoon"', '@click="showCard = true"')
text = text.replace('                    <p v-if="espaceNotice" class="text-wpx-muted-dark -mt-1 text-center text-xs">{{ espaceNotice }}</p>\n\n', '')
text = text.replace(
    '        <AccountSecurityPanel\n',
    '        <CardPanel :open="showCard" @close="showCard = false" />\n        <AccountSecurityPanel\n',
)
shell.write_text(text, encoding='utf-8')

roadmap = ROOT / 'docs/ROADMAP-INDEX.md'
text = roadmap.read_text(encoding='utf-8')
text = text.replace(
    '| P017 | Carte et partenaires | P003, P011, P012, P019 | Extension | En attente |',
    '| P017 | Carte et partenaires | P003, P011, P012, P019 | Extension | in_progress (P017-A : carte virtuelle, identifiant public, QR sécurisé et suspension) |',
)
roadmap.write_text(text, encoding='utf-8')
