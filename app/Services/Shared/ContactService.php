<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Shared\ContactServiceInterface;
use App\Models\Contacts\Contact;
use App\Services\Base\BaseService;

class ContactService extends BaseService implements ContactServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new contact.
     *
     * @param  array{name: string, type: string, email?: string, phone?: string, address?: string, tax_number?: string, is_customer?: bool, is_supplier?: bool, credit_limit?: int, payment_terms?: int}  $data
     */
    public function create(array $data): Contact
    {
        return $this->executeInTransaction('create_contact', function () use ($data) {
            return Contact::create($data);
        }, ['name' => $data['name']]);
    }

    /**
     * Update a contact.
     *
     * @param  array{name?: string, type?: string, email?: string, phone?: string, address?: string, tax_number?: string, is_customer?: bool, is_supplier?: bool, credit_limit?: int, payment_terms?: int}  $data
     */
    public function update(Contact $contact, array $data): Contact
    {
        return $this->executeInTransaction('update_contact', function () use ($contact, $data) {
            $contact->update($data);

            return $contact->fresh();
        }, ['contact_id' => $contact->id]);
    }

    /**
     * Delete a contact.
     */
    public function delete(Contact $contact): void
    {
        $this->executeInTransaction('delete_contact', function () use ($contact) {
            if ($contact->invoices()->exists() || $contact->bills()->exists()) {
                throw new \Exception('Tidak bisa menghapus kontak yang sudah memiliki transaksi.');
            }

            $contact->delete();
        }, ['contact_id' => $contact->id]);
    }
}
