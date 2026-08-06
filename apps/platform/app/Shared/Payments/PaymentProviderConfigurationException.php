<?php

declare(strict_types=1);

namespace App\Shared\Payments;

use RuntimeException;

/**
 * Thrown at boot/use time when the provider is misconfigured in a way that
 * must never reach production traffic — docs/chantiers/
 * HOTFIX-P003-GENIUSPAY-SANDBOX.md §4: "mode sandbox obligatoire; refus des
 * clés contenant live; HTTPS obligatoire; base API exacte".
 */
final class PaymentProviderConfigurationException extends RuntimeException {}
