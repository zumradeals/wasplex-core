<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

/**
 * docs/chantiers/P007-CHANTIER.md §2 : séparation optionnelle
 * demandeur/décideur — activée via config('campaigns.review_require_distinct_decider').
 */
final class SameAdminCannotDecideException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("L'administrateur ayant demandé la correction ne peut pas décider de cette resoumission.");
    }
}
