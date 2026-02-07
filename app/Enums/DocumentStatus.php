<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Centralized document status enum for all document types.
 *
 * Replaces scattered STATUS_* constants across models for type safety,
 * centralized labels, and workflow validation.
 */
enum DocumentStatus: string
{
    // Universal
    case Draft = 'draft';
    case Cancelled = 'cancelled';

    // Approval workflow
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    // Completion
    case Completed = 'completed';
    case Converted = 'converted';
    case Expired = 'expired';

    // Financial documents
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Received = 'received';
    case FullyApplied = 'fully_applied';
    case Refunded = 'refunded';

    // Manufacturing
    case Active = 'active';
    case Inactive = 'inactive';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Processing = 'processing';
    case Applied = 'applied';
    case Issued = 'issued';
    case Assigned = 'assigned';
    case Counting = 'counting';
    case Reviewed = 'reviewed';

    // Delivery
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Receiving = 'receiving';

    // Project
    case Planning = 'planning';
    case OnHold = 'on_hold';

    // Solar
    case Accepted = 'accepted';

    // Task
    case Todo = 'todo';
    case Done = 'done';

    // BOM
    case Archived = 'archived';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Cancelled => 'Dibatalkan',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Completed => 'Selesai',
            self::Converted => 'Dikonversi',
            self::Expired => 'Kedaluwarsa',
            self::Sent => 'Terkirim',
            self::Partial => 'Sebagian',
            self::Paid => 'Lunas',
            self::Overdue => 'Jatuh Tempo',
            self::Received => 'Diterima',
            self::FullyApplied => 'Selesai Digunakan',
            self::Refunded => 'Direfund',
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Confirmed => 'Dikonfirmasi',
            self::InProgress => 'Dalam Proses',
            self::Processing => 'Memproses',
            self::Applied => 'Diterapkan',
            self::Issued => 'Dikeluarkan',
            self::Assigned => 'Ditugaskan',
            self::Counting => 'Penghitungan',
            self::Reviewed => 'Direview',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Terkirim',
            self::Receiving => 'Menerima',
            self::Planning => 'Perencanaan',
            self::OnHold => 'Ditunda',
            self::Accepted => 'Diterima',
            self::Todo => 'Belum Dikerjakan',
            self::Done => 'Selesai',
            self::Archived => 'Diarsipkan',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Todo => 'zinc',
            self::Cancelled, self::Rejected => 'red',
            self::Submitted, self::Processing, self::Receiving, self::Reviewed => 'yellow',
            self::Approved, self::Completed, self::Paid, self::Delivered, self::Accepted, self::FullyApplied, self::Done => 'green',
            self::Converted, self::Active, self::Applied, self::Refunded, self::Counting => 'blue',
            self::Expired, self::Overdue => 'orange',
            self::Partial, self::InProgress, self::Assigned => 'indigo',
            self::Sent, self::Shipped, self::Issued => 'cyan',
            self::Confirmed, self::Received => 'emerald',
            self::Inactive, self::Archived, self::OnHold => 'gray',
            self::Planning => 'purple',
        };
    }

    /**
     * Check if this is an editable status (typically only draft).
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Todo], true);
    }

    /**
     * Check if this is a terminal/final status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Cancelled,
            self::Completed,
            self::Converted,
            self::Paid,
            self::Delivered,
            self::Expired,
            self::Done,
            self::Archived,
        ], true);
    }

    /**
     * Get statuses valid for Invoice documents.
     *
     * @return array<self>
     */
    public static function forInvoice(): array
    {
        return [
            self::Draft,
            self::Sent,
            self::Partial,
            self::Paid,
            self::Overdue,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for Bill documents.
     *
     * @return array<self>
     */
    public static function forBill(): array
    {
        return [
            self::Draft,
            self::Received,
            self::Partial,
            self::Paid,
            self::Overdue,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for Quotation documents.
     *
     * @return array<self>
     */
    public static function forQuotation(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::Approved,
            self::Rejected,
            self::Expired,
            self::Converted,
        ];
    }

    /**
     * Get statuses valid for PurchaseOrder documents.
     *
     * @return array<self>
     */
    public static function forPurchaseOrder(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::Approved,
            self::Rejected,
            self::Partial,
            self::Received,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for DeliveryOrder documents.
     *
     * @return array<self>
     */
    public static function forDeliveryOrder(): array
    {
        return [
            self::Draft,
            self::Confirmed,
            self::Shipped,
            self::Delivered,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for WorkOrder documents.
     *
     * @return array<self>
     */
    public static function forWorkOrder(): array
    {
        return [
            self::Draft,
            self::Confirmed,
            self::InProgress,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for BOM documents.
     *
     * @return array<self>
     */
    public static function forBom(): array
    {
        return [
            self::Draft,
            self::Active,
            self::Inactive,
        ];
    }

    /**
     * Get statuses valid for BomVariantGroup.
     *
     * @return array<self>
     */
    public static function forBomVariantGroup(): array
    {
        return [
            self::Draft,
            self::Active,
            self::Archived,
        ];
    }

    /**
     * Get statuses valid for MaterialRequisition documents.
     *
     * @return array<self>
     */
    public static function forMaterialRequisition(): array
    {
        return [
            self::Draft,
            self::Approved,
            self::Issued,
            self::Partial,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for Project documents.
     *
     * @return array<self>
     */
    public static function forProject(): array
    {
        return [
            self::Draft,
            self::Planning,
            self::InProgress,
            self::OnHold,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for SolarProposal documents.
     *
     * @return array<self>
     */
    public static function forSolarProposal(): array
    {
        return [
            self::Draft,
            self::Sent,
            self::Accepted,
            self::Rejected,
            self::Expired,
            self::Converted,
        ];
    }

    /**
     * Get statuses valid for GoodsReceiptNote documents.
     *
     * @return array<self>
     */
    public static function forGoodsReceiptNote(): array
    {
        return [
            self::Draft,
            self::Receiving,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for SalesReturn documents.
     *
     * @return array<self>
     */
    public static function forSalesReturn(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::Approved,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for PurchaseReturn documents.
     *
     * @return array<self>
     */
    public static function forPurchaseReturn(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::Approved,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for MrpRun.
     *
     * @return array<self>
     */
    public static function forMrpRun(): array
    {
        return [
            self::Draft,
            self::Processing,
            self::Completed,
            self::Applied,
        ];
    }

    /**
     * Get statuses valid for SubcontractorWorkOrder.
     *
     * @return array<self>
     */
    public static function forSubcontractorWorkOrder(): array
    {
        return [
            self::Draft,
            self::Assigned,
            self::InProgress,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for DownPayment documents.
     *
     * @return array<self>
     */
    public static function forDownPayment(): array
    {
        return [
            self::Active,
            self::FullyApplied,
            self::Refunded,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for StockOpname documents.
     *
     * @return array<self>
     */
    public static function forStockOpname(): array
    {
        return [
            self::Draft,
            self::Counting,
            self::Reviewed,
            self::Approved,
            self::Completed,
            self::Cancelled,
        ];
    }

    /**
     * Get statuses valid for Task documents.
     *
     * @return array<self>
     */
    public static function forTask(): array
    {
        return [
            self::Todo,
            self::InProgress,
            self::Done,
            self::Cancelled,
        ];
    }
}
