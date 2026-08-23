<?php

declare(strict_types=1);

namespace App\Contracts\Pos;

use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\Pos\PosSessionHold;
use Illuminate\Support\Collection;

interface PosServiceInterface
{
    /**
     * @param  array{warehouse_id: int, opening_cash_amount: int}  $data
     */
    public function openSession(array $data): PosSession;

    /**
     * @param  array{counted_cash_amount: int}  $data
     */
    public function closeSession(PosSession $session, array $data): PosSession;

    /**
     * @param  array{lines: list<array{product_id: int, quantity: int}>, way: string, cash_received_amount?: int}  $data
     */
    public function checkout(PosSession $session, array $data, string $idempotencyKey): PosSale;

    public function voidSale(PosSession $session, PosSale $sale, string $reason): PosSale;

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function hold(PosSession $session, array $lines): PosSessionHold;

    /**
     * @return Collection<int, PosSessionHold>
     */
    public function listHolds(PosSession $session): Collection;

    public function takeHold(PosSession $session, PosSessionHold $hold): PosSessionHold;

    public function expectedCash(PosSession $session): int;

    public function currentOpenSession(int $userId): ?PosSession;
}
