<?php

namespace App\Modules\Ledger\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LedgerQueryContract
{
    /** @return array<string, mixed>|null */
    public function transaction(string $transactionId): ?array;

    /**
     * @param array<string, string> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function transactions(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function accounts(int $perPage = 25): LengthAwarePaginator;
}
