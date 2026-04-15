<?php

namespace App\Services;

/**
 * DTO con el resultado de la resolución de un LID.
 */
class LidResolutionResult
{
    public function __construct(
        public readonly bool $resolved,
        public readonly ?string $phoneNumber,
        public readonly string $jid,
        public readonly ?string $lidIdentifier,
        public readonly string $method,
    ) {}

    /**
     * ¿Es un LID sin número real?
     */
    public function isUnresolvedLid(): bool
    {
        return !$this->resolved && $this->lidIdentifier !== null;
    }
}
