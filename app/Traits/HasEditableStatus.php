<?php

namespace App\Traits;

use App\Enums\DocumentStatus;

trait HasEditableStatus
{
    public function isEditable(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isDeletable(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }
}
