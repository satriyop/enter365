<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

trait AuthorizesPosPermission
{
    abstract protected function requiredPosPermission(): string;

    abstract protected function unauthorizedPosMessage(): string;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission($this->requiredPosPermission());
    }

    protected function failedAuthorization(): void
    {
        abort(403, $this->unauthorizedPosMessage());
    }
}
