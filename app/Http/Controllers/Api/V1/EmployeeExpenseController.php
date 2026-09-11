<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\EmployeeExpenseServiceInterface;
use App\Http\Requests\Api\V1\StoreEmployeeExpenseRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeExpenseRequest;
use App\Http\Resources\Api\V1\EmployeeExpenseResource;
use App\Models\Accounting\EmployeeExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeExpenseController extends Controller
{
    public function __construct(
        private EmployeeExpenseServiceInterface $expenses,
    ) {}

    /**
     * List employee expenses for accounting (Odoo Vendors › Expenses).
     *
     * @queryParam search string Search number or description. Example: EXP
     * @queryParam status string draft, submitted, approved, posted, refused, cancelled. Example: approved
     * @queryParam employee_id int Filter by employee. Example: 1
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EmployeeExpense::class);

        $query = EmployeeExpense::query()
            ->with(['employee', 'contact', 'expenseAccount'])
            ->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('expense_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }
        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }
        if ($employeeId = $request->integer('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        return EmployeeExpenseResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreEmployeeExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', EmployeeExpense::class);

        $expense = $this->expenses->create($request->validated());

        return (new EmployeeExpenseResource($expense->load(['employee', 'contact', 'expenseAccount'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('view', $employeeExpense);

        return new EmployeeExpenseResource($employeeExpense->load([
            'employee',
            'contact',
            'expenseAccount',
            'journalEntry',
        ]));
    }

    public function update(UpdateEmployeeExpenseRequest $request, EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource($this->expenses->update($employeeExpense, $request->validated()));
    }

    public function destroy(EmployeeExpense $employeeExpense): JsonResponse
    {
        $this->authorize('delete', $employeeExpense);

        $this->expenses->delete($employeeExpense);

        return $this->deleted('Biaya karyawan berhasil dihapus.');
    }

    public function submit(EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource($this->expenses->submit($employeeExpense));
    }

    public function approve(EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource($this->expenses->approve($employeeExpense));
    }

    public function refuse(Request $request, EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource(
            $this->expenses->refuse($employeeExpense, $request->string('reason')->trim()->toString() ?: null)
        );
    }

    public function post(EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource($this->expenses->post($employeeExpense));
    }

    public function cancel(Request $request, EmployeeExpense $employeeExpense): EmployeeExpenseResource
    {
        $this->authorize('update', $employeeExpense);

        return new EmployeeExpenseResource(
            $this->expenses->cancel($employeeExpense, $request->string('reason')->trim()->toString() ?: null)
        );
    }
}
