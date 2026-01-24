<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\ContactFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Contacts\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(ContactFilter $filter): AnonymousResourceCollection
    {
        $contacts = Contact::query()
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = Contact::create($request->validated());

        return new ContactResource($contact);
    }

    public function show(Contact $contact, ContactFilter $filter): ContactResource
    {
        $filter->apply($contact->newQuery());

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($request->validated());

        return new ContactResource($contact->fresh());
    }

    public function destroy(Contact $contact): JsonResponse
    {
        if ($contact->invoices()->exists() || $contact->bills()->exists()) {
            abort(422, 'Tidak bisa menghapus kontak yang sudah memiliki transaksi.');
        }

        $contact->delete();

        return response()->json(['message' => 'Kontak berhasil dihapus.']);
    }

    public function balances(Contact $contact): JsonResponse
    {
        return response()->json([
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'type' => $contact->type,
            'receivable_balance' => $contact->isCustomer() ? $contact->getReceivableBalance() : null,
            'payable_balance' => $contact->isSupplier() ? $contact->getPayableBalance() : null,
        ]);
    }

    public function creditStatus(Contact $contact): JsonResponse
    {
        if (! $contact->isCustomer()) {
            return response()->json([
                'message' => 'Status kredit hanya tersedia untuk pelanggan.',
            ], 422);
        }

        $receivableBalance = $contact->getReceivableBalance();
        $creditLimit = $contact->credit_limit;
        $availableCredit = $contact->getAvailableCredit();
        $utilization = $contact->getCreditUtilization();

        return response()->json([
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'credit_limit' => $creditLimit,
            'receivable_balance' => $receivableBalance,
            'available_credit' => $availableCredit,
            'credit_utilization_percent' => round($utilization, 2),
            'is_exceeded' => $contact->isCreditLimitExceeded(),
            'is_warning' => $contact->isCreditLimitWarning(),
            'can_create_invoice' => $contact->canCreateInvoice(),
            'last_transaction_date' => $contact->last_transaction_date?->toDateString(),
        ]);
    }
}
