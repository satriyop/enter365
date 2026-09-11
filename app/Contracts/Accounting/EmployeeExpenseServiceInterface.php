<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\EmployeeExpense;

interface EmployeeExpenseServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeExpense;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeExpense $expense, array $data): EmployeeExpense;

    public function delete(EmployeeExpense $expense): void;

    public function submit(EmployeeExpense $expense): EmployeeExpense;

    public function approve(EmployeeExpense $expense): EmployeeExpense;

    public function refuse(EmployeeExpense $expense, ?string $reason = null): EmployeeExpense;

    public function post(EmployeeExpense $expense): EmployeeExpense;

    public function cancel(EmployeeExpense $expense, ?string $reason = null): EmployeeExpense;
}
