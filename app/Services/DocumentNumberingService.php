<?php

namespace App\Services;

use App\Models\DocumentCounter;
use Illuminate\Support\Facades\DB;

/**
 * DocumentNumberingService - Per-tenant document sequence generation
 *
 * Generates sequential document numbers in the format: {PREFIX}-{PERIOD}-{NUMBER}
 * Example: SO-202510-0001, PO-202510-0042, INV-202510-0123
 */
class DocumentNumberingService
{
    /**
     * Generate next document number for a company
     *
     * @param int $companyId
     * @param string $docType Document type (SO, PO, INV, etc.)
     * @param string|null $period Period in YYYYMM format (defaults to current month)
     * @param int $padLength Zero-padding length (default 4)
     * @return string Generated document number
     */
    public function generateNumber(
        int $companyId,
        string $docType,
        ?string $period = null,
        int $padLength = 4
    ): string {
        $period = $period ?? date('Ym'); // YYYYMM format

        return DB::transaction(function () use ($companyId, $docType, $period, $padLength) {
            // Lock and increment counter atomically
            $counter = DocumentCounter::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'doc_type' => $docType,
                    'period' => $period,
                ],
                [
                    'last_num' => 0,
                ]
            );

            // Lock the row for update
            $counter = DocumentCounter::where('id', $counter->id)
                ->lockForUpdate()
                ->first();

            // Increment the counter
            $nextNum = $counter->last_num + 1;
            $counter->update(['last_num' => $nextNum]);

            // Format: PREFIX-PERIOD-NUMBER
            return sprintf(
                '%s-%s-%s',
                strtoupper($docType),
                $period,
                str_pad($nextNum, $padLength, '0', STR_PAD_LEFT)
            );
        });
    }

    /**
     * Generate multiple sequential numbers in one transaction
     *
     * @param int $companyId
     * @param string $docType
     * @param int $count How many numbers to generate
     * @param string|null $period
     * @param int $padLength
     * @return array Array of generated document numbers
     */
    public function generateBatch(
        int $companyId,
        string $docType,
        int $count,
        ?string $period = null,
        int $padLength = 4
    ): array {
        $period = $period ?? date('Ym');

        return DB::transaction(function () use ($companyId, $docType, $count, $period, $padLength) {
            $counter = DocumentCounter::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'doc_type' => $docType,
                    'period' => $period,
                ],
                [
                    'last_num' => 0,
                ]
            );

            $counter = DocumentCounter::where('id', $counter->id)
                ->lockForUpdate()
                ->first();

            $startNum = $counter->last_num + 1;
            $endNum = $startNum + $count - 1;

            $counter->update(['last_num' => $endNum]);

            $numbers = [];
            for ($i = $startNum; $i <= $endNum; $i++) {
                $numbers[] = sprintf(
                    '%s-%s-%s',
                    strtoupper($docType),
                    $period,
                    str_pad($i, $padLength, '0', STR_PAD_LEFT)
                );
            }

            return $numbers;
        });
    }

    /**
     * Get current counter value (without incrementing)
     *
     * @param int $companyId
     * @param string $docType
     * @param string|null $period
     * @return int
     */
    public function getCurrentNumber(
        int $companyId,
        string $docType,
        ?string $period = null
    ): int {
        $period = $period ?? date('Ym');

        $counter = DocumentCounter::where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->where('period', $period)
            ->first();

        return $counter ? $counter->last_num : 0;
    }

    /**
     * Reset counter for a specific period (use with caution!)
     *
     * @param int $companyId
     * @param string $docType
     * @param string $period
     * @return bool
     */
    public function resetCounter(
        int $companyId,
        string $docType,
        string $period
    ): bool {
        return DocumentCounter::where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->where('period', $period)
            ->update(['last_num' => 0]) > 0;
    }
}
