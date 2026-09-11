<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\Loan;

interface LoanServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Loan;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Loan $loan, array $data): Loan;

    public function delete(Loan $loan): void;

    public function confirm(Loan $loan): Loan;

    public function postNextInstallment(Loan $loan): Loan;

    /**
     * @return array<string, mixed>
     */
    public function analysis(): array;
}
