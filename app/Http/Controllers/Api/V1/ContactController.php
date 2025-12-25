<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Accounting\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::query();

        if ($request->has('type')) {
            $type = $request->input('type');
            if ($type === Contact::TYPE_CUSTOMER) {
                $query->whereIn('type', [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH]);
            } elseif ($type === Contact::TYPE_SUPPLIER) {
                $query->whereIn('type', [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH]);
            } else {
                $query->where('type', $type);
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        $contacts = $query->orderBy('name')->paginate($request->input('per_page', 25));

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = Contact::create($request->validated());

        return new ContactResource($contact);
    }

    public function show(Contact $contact): ContactResource
    {
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
        if (!$contact->isCustomer()) {
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
