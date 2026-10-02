<?php

declare(strict_types=1);

namespace App\DTO;

readonly class TransactionInstructionDTO
{
    public function __construct(
        public string $idempotencyKey,
        public string $sourceReference,
        public string $destinationReference,
        public string $amount,
        public string $operationType,
        public array $metadata = []
    ) {}

    public function generatePayloadHash(): string
    {
        return hash('sha256', json_encode([
            'src'    => $this->sourceReference,
            'dst'    => $this->destinationReference,
            'amount' => $this->amount,
            'op'     => $this->operationType,
        ], JSON_THROW_ON_ERROR));
    }
}