<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;

final class CanonicalPayloadHasher
{
    public function hash(PostingRequest $request): string
    {
        $entries = array_map(static fn (PostingEntry $entry): array => [
            'account_id' => $entry->accountId,
            'direction' => $entry->direction->value,
            'amount_minor' => $entry->amountMinor,
            'description' => $entry->description,
            'external_reference' => $entry->externalReference,
        ], $request->entries);

        usort($entries, static fn (array $left, array $right): int => json_encode($left) <=> json_encode($right));

        $payload = $this->normalize([
            'journal_code' => strtoupper($request->journalCode),
            'type' => $request->type,
            'source_module' => $request->sourceModule,
            'business_reference' => $request->businessReference,
            'unit' => strtoupper($request->unit),
            'currency' => strtoupper($request->currency),
            'entries' => $entries,
            'actor_account_id' => $request->actorAccountId,
            'beneficiary_account_id' => $request->beneficiaryAccountId,
            'justification' => $request->justification,
            'rule_version' => $request->ruleVersion,
            'metadata' => $request->metadata,
            'posted_at' => $request->postedAt?->format(DATE_ATOM),
        ]);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
