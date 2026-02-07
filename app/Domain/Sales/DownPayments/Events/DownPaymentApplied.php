<?php

declare(strict_types=1);

namespace App\Domain\Sales\DownPayments\Events;

use App\Models\Sales\DownPaymentApplication;

readonly class DownPaymentApplied
{
    public function __construct(
        public int $downPaymentId,
        public string $dpNumber,
        public int $applicationId,
        public string $applicableType,
        public int $applicableId,
        public int $amount,
        public int $userId,
        public \Carbon\Carbon $appliedAt
    ) {}

    public static function fromApplication(DownPaymentApplication $application, int $userId): self
    {
        $dp = $application->downPayment;

        return new self(
            downPaymentId: $dp->id,
            dpNumber: $dp->dp_number,
            applicationId: $application->id,
            applicableType: $application->applicable_type,
            applicableId: $application->applicable_id,
            amount: $application->amount,
            userId: $userId,
            appliedAt: now()
        );
    }
}
