<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\FiscalPosition;
use App\Models\Contacts\Contact;

interface FiscalPositionServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FiscalPosition;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FiscalPosition $position, array $data): FiscalPosition;

    public function delete(FiscalPosition $position): void;

    public function forContact(?Contact $contact): ?FiscalPosition;

    public function forContactId(?int $contactId): ?FiscalPosition;

    /**
     * @param  list<int>  $taxRecordIds
     * @return list<int>
     */
    public function mapTaxRecordIds(?FiscalPosition $position, array $taxRecordIds): array;

    public function mapAccountId(?FiscalPosition $position, ?int $accountId): ?int;
}
