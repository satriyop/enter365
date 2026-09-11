<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\EmployeeExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeExpense
 */
class EmployeeExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'employee_id' => $this->employee_id,
            'contact_id' => $this->contact_id,
            'expense_date' => $this->expense_date?->toDateString(),
            'description' => $this->description,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'expense_account_id' => $this->expense_account_id,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'email' => $this->employee->email,
            ]),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
            ]),
            'expense_account' => $this->whenLoaded('expenseAccount', fn () => $this->expenseAccount === null ? null : [
                'id' => $this->expenseAccount->id,
                'code' => $this->expenseAccount->code,
                'name' => $this->expenseAccount->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
