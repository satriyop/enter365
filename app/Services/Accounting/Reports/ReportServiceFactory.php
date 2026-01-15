<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Services\Accounting\AccountBalanceService;
use App\Services\Inventory\Reports\COGSReportService;
use App\Services\Manufacturing\Reports\SubcontractorReportService;
use App\Services\Manufacturing\Reports\WorkOrderReportService;
use App\Services\Projects\ProjectReportService;
use InvalidArgumentException;

/**
 * Factory for creating report service instances.
 *
 * This factory centralizes report service resolution, reducing
 * the number of dependencies in the ReportController.
 */
class ReportServiceFactory
{
    public const TYPE_BALANCE = 'balance';

    public const TYPE_FINANCIAL = 'financial';

    public const TYPE_AGING = 'aging';

    public const TYPE_TAX = 'tax';

    public const TYPE_CASH_FLOW = 'cash_flow';

    public const TYPE_PROJECT = 'project';

    public const TYPE_WORK_ORDER = 'work_order';

    public const TYPE_SUBCONTRACTOR = 'subcontractor';

    public const TYPE_BANK_RECONCILIATION = 'bank_reconciliation';

    public const TYPE_COGS = 'cogs';

    /**
     * Get a report service instance by type.
     *
     * @throws InvalidArgumentException If the report type is not supported.
     */
    public function make(string $type): object
    {
        return match ($type) {
            self::TYPE_BALANCE => app(AccountBalanceService::class),
            self::TYPE_FINANCIAL => app(FinancialReportService::class),
            self::TYPE_AGING => app(AgingReportService::class),
            self::TYPE_TAX => app(TaxReportService::class),
            self::TYPE_CASH_FLOW => app(CashFlowReportService::class),
            self::TYPE_PROJECT => app(ProjectReportService::class),
            self::TYPE_WORK_ORDER => app(WorkOrderReportService::class),
            self::TYPE_SUBCONTRACTOR => app(SubcontractorReportService::class),
            self::TYPE_BANK_RECONCILIATION => app(BankReconciliationReportService::class),
            self::TYPE_COGS => app(COGSReportService::class),
            default => throw new InvalidArgumentException("Unknown report type: {$type}"),
        };
    }

    /**
     * Get the balance service.
     */
    public function balance(): AccountBalanceService
    {
        return $this->make(self::TYPE_BALANCE);
    }

    /**
     * Get the financial report service.
     */
    public function financial(): FinancialReportService
    {
        return $this->make(self::TYPE_FINANCIAL);
    }

    /**
     * Get the aging report service.
     */
    public function aging(): AgingReportService
    {
        return $this->make(self::TYPE_AGING);
    }

    /**
     * Get the tax report service.
     */
    public function tax(): TaxReportService
    {
        return $this->make(self::TYPE_TAX);
    }

    /**
     * Get the cash flow report service.
     */
    public function cashFlow(): CashFlowReportService
    {
        return $this->make(self::TYPE_CASH_FLOW);
    }

    /**
     * Get the project report service.
     */
    public function project(): ProjectReportService
    {
        return $this->make(self::TYPE_PROJECT);
    }

    /**
     * Get the work order report service.
     */
    public function workOrder(): WorkOrderReportService
    {
        return $this->make(self::TYPE_WORK_ORDER);
    }

    /**
     * Get the subcontractor report service.
     */
    public function subcontractor(): SubcontractorReportService
    {
        return $this->make(self::TYPE_SUBCONTRACTOR);
    }

    /**
     * Get the bank reconciliation report service.
     */
    public function bankReconciliation(): BankReconciliationReportService
    {
        return $this->make(self::TYPE_BANK_RECONCILIATION);
    }

    /**
     * Get the COGS report service.
     */
    public function cogs(): COGSReportService
    {
        return $this->make(self::TYPE_COGS);
    }

    /**
     * Get all available report types.
     *
     * @return array<string>
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_BALANCE,
            self::TYPE_FINANCIAL,
            self::TYPE_AGING,
            self::TYPE_TAX,
            self::TYPE_CASH_FLOW,
            self::TYPE_PROJECT,
            self::TYPE_WORK_ORDER,
            self::TYPE_SUBCONTRACTOR,
            self::TYPE_BANK_RECONCILIATION,
            self::TYPE_COGS,
        ];
    }
}
