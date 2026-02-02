<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\ContactFilter;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Contacts\Contact;
use App\Services\Shared\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function __construct(
        private ContactService $contactService
    ) {}

    public function index(ContactFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $this->authorize('create', Contact::class);

        $contact = $this->contactService->create($request->validated());

        return new ContactResource($contact);
    }

    public function show(Contact $contact, ContactFilter $filter): ContactResource
    {
        $this->authorize('view', $contact);

        $filter->apply($contact->newQuery());

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $this->authorize('update', $contact);

        $updatedContact = $this->contactService->update($contact, $request->validated());

        return new ContactResource($updatedContact);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        try {
            $this->contactService->delete($contact);

            return $this->deleted('Kontak berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function balances(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return $this->success([
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'type' => $contact->type,
            'receivable_balance' => $contact->isCustomer() ? $contact->getReceivableBalance() : null,
            'payable_balance' => $contact->isSupplier() ? $contact->getPayableBalance() : null,
        ]);
    }

    public function creditStatus(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        if (! $contact->isCustomer()) {
            return $this->error('Status kredit hanya tersedia untuk pelanggan.', 422);
        }

        $receivableBalance = $contact->getReceivableBalance();
        $creditLimit = $contact->credit_limit;
        $availableCredit = $contact->getAvailableCredit();
        $utilization = $contact->getCreditUtilization();

        /** @var \Carbon\Carbon|null $lastTransactionDate */
        $lastTransactionDate = $contact->last_transaction_date;

        return $this->success([
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'credit_limit' => $creditLimit,
            'receivable_balance' => $receivableBalance,
            'available_credit' => $availableCredit,
            'credit_utilization_percent' => round($utilization, 2),
            'is_exceeded' => $contact->isCreditLimitExceeded(),
            'is_warning' => $contact->isCreditLimitWarning(),
            'can_create_invoice' => $contact->canCreateInvoice(),
            'last_transaction_date' => $lastTransactionDate?->toDateString(),
        ]);
    }
}
