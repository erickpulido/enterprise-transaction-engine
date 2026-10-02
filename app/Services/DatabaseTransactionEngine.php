<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TransactionEngineInterface;
use App\DTO\TransactionInstructionDTO;
use App\Exceptions\AccountUnavailableException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProjectTransactionToClickHouseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseTransactionEngine implements TransactionEngineInterface
{
    public function execute(TransactionInstructionDTO $instruction): array
    {
        $payloadHash = $instruction->generatePayloadHash();

        return DB::transaction(function () use ($instruction, $payloadHash): array {
            // 1. Verificación pesimista de Idempotencia
            $existing = DB::table('idempotency_keys')
                ->where('key', $instruction->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->request_hash !== $payloadHash) {
                    throw new IdempotencyConflictException(
                        "La clave '{$instruction->idempotencyKey}' ya fue utilizada con un payload diferente."
                    );
                }
                return json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR);
            }

            // 2. Ordenamiento de claves para prevenir deadlocks en transacciones bidireccionales concurrentes
            $targets = [$instruction->sourceReference, $instruction->destinationReference];
            sort($targets, SORT_STRING);

            $accounts = DB::table('accounts')
                ->whereIn('reference_id', $targets)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('reference_id');

            if ($accounts->count() !== 2) {
                throw new AccountUnavailableException("Una o ambas cuentas no existen o se encuentran inactivas.");
            }

            $source = $accounts->get($instruction->sourceReference);
            $destination = $accounts->get($instruction->destinationReference);

            // 3. Validación de saldo disponible
            if (bccomp((string) $source->balance, $instruction->amount, 4) < 0) {
                throw new InsufficientFundsException(
                    "Fondos insuficientes en {$source->reference_id}. Requerido: {$instruction->amount}, Disponible: {$source->balance}"
                );
            }

            // 4. Modificación de balances con precisión numérica
            DB::table('accounts')
                ->where('id', $source->id)
                ->decrement('balance', (float) $instruction->amount);

            DB::table('accounts')
                ->where('id', $destination->id)
                ->increment('balance', (float) $instruction->amount);

            // 5. Inserción en Ledger
            $txUuid = Str::uuid()->toString();
            $now = now()->toDateTimeString();

            DB::table('ledger_entries')->insert([
                'transaction_uuid'       => $txUuid,
                'source_account_id'      => $source->id,
                'destination_account_id' => $destination->id,
                'amount'                 => $instruction->amount,
                'operation_type'         => $instruction->operationType,
                'metadata'               => json_encode($instruction->metadata, JSON_THROW_ON_ERROR),
                'created_at'             => $now,
            ]);

            $responsePayload = [
                'status'           => 'COMMITTED',
                'transaction_uuid' => $txUuid,
                'amount'           => $instruction->amount,
                'processed_at'     => $now,
            ];

            // 6. Registro del resultado en tabla de idempotencia
            DB::table('idempotency_keys')->insert([
                'key'           => $instruction->idempotencyKey,
                'request_hash'  => $payloadHash,
                'response_code' => 200,
                'response_body' => json_encode($responsePayload, JSON_THROW_ON_ERROR),
                'created_at'    => $now,
            ]);

            // 7. Despacho asíncrono hacia ClickHouse condicionado al COMMIT
            $olapPayload = [
                'transaction_uuid'       => $txUuid,
                'source_account_id'      => (int) $source->id,
                'destination_account_id' => (int) $destination->id,
                'amount'                 => (float) $instruction->amount,
                'operation_type'         => $instruction->operationType,
                'metadata_json'          => json_encode($instruction->metadata, JSON_THROW_ON_ERROR),
                'committed_at'           => $now,
            ];

            DB::afterCommit(static function () use ($olapPayload): void {
                ProjectTransactionToClickHouseJob::dispatch($olapPayload)->onQueue('telemetry');
            });

            return $responsePayload;
        }, 5); // 5 reintentos en caso de colisión de concurrencia serializada
    }
}