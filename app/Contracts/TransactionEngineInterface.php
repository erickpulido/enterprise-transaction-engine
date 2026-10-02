<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\TransactionInstructionDTO;

interface TransactionEngineInterface
{
    public function execute(TransactionInstructionDTO $instruction): array;
}