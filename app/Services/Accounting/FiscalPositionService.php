<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\FiscalPositionServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\FiscalPosition;
use App\Models\Contacts\Contact;
use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithRequestCache;
use Illuminate\Support\Arr;

class FiscalPositionService extends BaseService implements FiscalPositionServiceInterface
{
    use WithRequestCache;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FiscalPosition
    {
        return $this->executeInTransaction('create_fiscal_position', function () use ($data) {
            $position = FiscalPosition::query()->create(Arr::only($data, ['code', 'name', 'notes', 'is_active']));
            $this->syncMaps($position, $data);

            return $this->loadMaps($position);
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FiscalPosition $position, array $data): FiscalPosition
    {
        return $this->executeInTransaction('update_fiscal_position', function () use ($position, $data) {
            $position->update(Arr::only($data, ['code', 'name', 'notes', 'is_active']));
            if (array_key_exists('tax_maps', $data) || array_key_exists('account_maps', $data)) {
                $this->syncMaps($position, $data);
            }

            return $this->loadMaps($position);
        }, ['fiscal_position_id' => $position->id]);
    }

    public function delete(FiscalPosition $position): void
    {
        $this->executeInTransaction('delete_fiscal_position', function () use ($position) {
            if ($position->contacts()->exists()) {
                throw new BusinessRuleException(
                    'Posisi fiskal tidak bisa dihapus karena masih dipakai kontak.',
                    ['fiscal_position_id' => $position->id]
                );
            }

            $position->taxMaps()->delete();
            $position->accountMaps()->delete();
            $position->delete();
        }, ['fiscal_position_id' => $position->id]);
    }

    public function forContact(?Contact $contact): ?FiscalPosition
    {
        if ($contact === null) {
            return null;
        }

        return $this->forContactId((int) $contact->id);
    }

    public function forContactId(?int $contactId): ?FiscalPosition
    {
        if ($contactId === null) {
            return null;
        }

        /** @var FiscalPosition|null */
        return $this->cached('fiscal-position-contact-'.$contactId, function () use ($contactId) {
            $contact = Contact::query()->select(['id', 'fiscal_position_id'])->find($contactId);
            if ($contact === null || $contact->fiscal_position_id === null) {
                return null;
            }

            return FiscalPosition::query()
                ->with(['taxMaps', 'accountMaps'])
                ->find($contact->fiscal_position_id);
        });
    }

    /**
     * @param  list<int>  $taxRecordIds
     * @return list<int>
     */
    public function mapTaxRecordIds(?FiscalPosition $position, array $taxRecordIds): array
    {
        if ($position === null) {
            return array_values(array_unique(array_map('intval', $taxRecordIds)));
        }

        $position->loadMissing(['taxMaps']);

        return $position->mapTaxRecordIds($taxRecordIds);
    }

    public function mapAccountId(?FiscalPosition $position, ?int $accountId): ?int
    {
        if ($position === null) {
            return $accountId;
        }

        $position->loadMissing(['accountMaps']);

        return $position->mapAccountId($accountId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMaps(FiscalPosition $position, array $data): void
    {
        if (array_key_exists('tax_maps', $data)) {
            $position->taxMaps()->delete();
            foreach ($data['tax_maps'] ?? [] as $map) {
                $position->taxMaps()->create([
                    'source_tax_record_id' => (int) $map['source_tax_record_id'],
                    'dest_tax_record_id' => isset($map['dest_tax_record_id'])
                        ? (int) $map['dest_tax_record_id']
                        : null,
                ]);
            }
        }

        if (array_key_exists('account_maps', $data)) {
            $position->accountMaps()->delete();
            foreach ($data['account_maps'] ?? [] as $map) {
                $position->accountMaps()->create([
                    'source_account_id' => (int) $map['source_account_id'],
                    'dest_account_id' => (int) $map['dest_account_id'],
                ]);
            }
        }
    }

    private function loadMaps(FiscalPosition $position): FiscalPosition
    {
        return $position->fresh(['taxMaps.sourceTax', 'taxMaps.destTax', 'accountMaps.sourceAccount', 'accountMaps.destAccount'])
            ?? $position;
    }
}
