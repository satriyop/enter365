<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\Models\Contacts\Contact;

/**
 * Interface for Contact (customer/supplier) management.
 */
interface ContactServiceInterface
{
    /**
     * Create a new contact.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact;

    /**
     * Update an existing contact.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data): Contact;

    /**
     * Delete a contact.
     */
    public function delete(Contact $contact): void;
}
