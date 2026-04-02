<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReconciliationException;
use App\Models\User;
use App\Repositories\AnomalyAlertRepository;
use App\Repositories\ReconciliationExceptionRepository;
use App\Repositories\SettlementImportRepository;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationService
{
    public const ANOMALY_THRESHOLD = 50.0;

    public function __construct(
        private readonly SettlementImportRepository $settlementImportRepository,
        private readonly ReconciliationExceptionRepository $reconciliationExceptionRepository,
        private readonly AnomalyAlertRepository $anomalyAlertRepository,
    ) {
    }

    public function importSettlement(UploadedFile $file, User $actor): array
    {
        $content = $file->get();

        if (trim((string) $content) === '') {
            return [
                'success' => false,
                'error' => 'EMPTY_FILE',
                'status' => 422,
                'data' => [],
            ];
        }

        $fileHash = hash('sha256', $content);

        if ($this->settlementImportRepository->findByFileHash($fileHash)) {
            Log::channel('reconciliation')->info('Duplicate settlement import detected', [
                'event' => 'import_duplicate',
                'fingerprint' => $fileHash,
                'filename' => $file->getClientOriginalName(),
                'actor_id' => $actor->id,
            ]);

            return [
                'success' => false,
                'error' => 'DUPLICATE_SETTLEMENT_FILE',
                'status' => 409,
                'data' => [],
            ];
        }

        $parsed = $this->parseCsv($content);
        $requiredColumns = ['transaction_id', 'amount', 'type', 'timestamp', 'terminal_id'];
        $missingRequiredColumns = array_values(array_diff($requiredColumns, $parsed['headers']));

        if (! empty($missingRequiredColumns)) {
            return [
                'success' => false,
                'error' => 'INVALID_FILE_FORMAT',
                'status' => 422,
                'data' => ['missing_columns' => $missingRequiredColumns],
            ];
        }

        if (count($parsed['rows']) === 0) {
            return [
                'success' => false,
                'error' => 'EMPTY_FILE',
                'status' => 422,
                'data' => [],
            ];
        }

        Log::channel('reconciliation')->info('Settlement import started', [
            'event' => 'import_started',
            'rows' => count($parsed['rows']),
            'filename' => $file->getClientOriginalName(),
            'actor_id' => $actor->id,
        ]);

        return DB::transaction(function () use ($file, $actor, $fileHash, $parsed, $requiredColumns): array {
            $rowCount = 0;
            $matchedCount = 0;
            $discrepancyCount = 0;
            $sumExpected = 0.0;
            $sumActual = 0.0;
            $errors = $parsed['errors'];

            $import = $this->settlementImportRepository->create([
                'filename' => $file->getClientOriginalName() ?: 'settlement.csv',
                'file_hash' => $fileHash,
                'imported_by' => $actor->id,
                'site_id' => (int) $actor->site_id,
                'row_count' => 0,
                'matched_count' => 0,
                'discrepancy_count' => 0,
                'daily_variance' => 0,
                'created_at' => now(),
            ]);

            foreach ($parsed['rows'] as $index => $row) {
                $rowCount++;
                $missingColumns = array_values(array_filter($requiredColumns, fn ($column) => ! array_key_exists($column, $row)));

                if (! empty($missingColumns)) {
                    $errors[] = [
                        'row' => $index + 2,
                        'error' => 'MISSING_COLUMNS',
                        'missing' => $missingColumns,
                    ];
                    continue;
                }

                $match = $this->matchRow($row, $import->id);

                $sumExpected += $match['expected_amount'];
                $sumActual += $match['actual_amount'];

                if ($match['matched']) {
                    $matchedCount++;
                } else {
                    $discrepancyCount++;
                }
            }

            $dailyVariance = abs($sumActual - $sumExpected);

            $import->row_count = $rowCount;
            $import->matched_count = $matchedCount;
            $import->discrepancy_count = $discrepancyCount;
            $import->daily_variance = round($dailyVariance, 2);
            $import->save();

            $anomalyAlert = false;
            if ($dailyVariance > self::ANOMALY_THRESHOLD) {
                $this->anomalyAlertRepository->create([
                    'import_id' => $import->id,
                    'site_id' => (int) $actor->site_id,
                    'variance_amount' => round($dailyVariance, 2),
                    'status' => 'unresolved',
                    'created_at' => now(),
                ]);

                $anomalyAlert = true;
            }

            AuditLogger::write(
                $actor->id,
                'IMPORT_SETTLEMENT',
                'settlement_imports',
                $import->id,
                [
                    'filename' => $import->filename,
                    'row_count' => $rowCount,
                    'matched_count' => $matchedCount,
                    'discrepancy_count' => $discrepancyCount,
                    'daily_variance' => $import->daily_variance,
                ],
                request()?->ip(),
            );

            Log::channel('reconciliation')->info('Settlement import completed', [
                'event' => 'import_completed',
                'matched' => $matchedCount,
                'exceptions' => $discrepancyCount,
                'import_id' => $import->id,
                'actor_id' => $actor->id,
            ]);

            return [
                'success' => true,
                'status' => 201,
                'data' => [
                    'import' => $import,
                    'anomaly_alert' => $anomalyAlert,
                    'anomaly_threshold' => self::ANOMALY_THRESHOLD,
                    'errors' => $errors,
                ],
            ];
        });
    }

    public function resolveException(ReconciliationException $exception, User $reviewer, string $note): array
    {
        if ($exception->status === 'resolved') {
            return [
                'success' => false,
                'error' => 'ALREADY_RESOLVED',
                'status' => 422,
                'data' => ['message' => 'This exception has already been resolved.'],
            ];
        }

        $siteId = (int) ($exception->settlementImport?->site_id ?? 0);

        if ($reviewer->role !== 'administrator' && $siteId !== (int) $reviewer->site_id) {
            return [
                'success' => false,
                'error' => 'FORBIDDEN',
                'status' => 403,
                'data' => [],
            ];
        }

        $exception->status = 'resolved';
        $exception->resolved_by = $reviewer->id;
        $exception->resolution_note = $note;
        $exception->resolved_at = now();
        $this->reconciliationExceptionRepository->save($exception);

        AuditLogger::write(
            $reviewer->id,
            'RESOLVE_EXCEPTION',
            ReconciliationException::class,
            $exception->id,
            ['note' => $note],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 200,
            'data' => ['exception' => $exception->fresh(['settlementImport', 'resolvedBy'])],
        ];
    }

    private function matchRow(array $row, int $importId): array
    {
        $referenceId = (string) ($row['transaction_id'] ?? '');
        $actualAmount = (float) ($row['amount'] ?? 0);

        $payment = Payment::withoutGlobalScopes()
            ->where('reference_id', $referenceId)
            ->first();

        if (! $payment) {
            $this->reconciliationExceptionRepository->create([
                'import_id' => $importId,
                'row_data' => $row,
                'expected_amount' => null,
                'actual_amount' => $actualAmount,
                'reason' => 'ORDER_NOT_FOUND',
                'status' => 'unresolved',
                'created_at' => now(),
            ]);

            return ['matched' => false, 'expected_amount' => 0.0, 'actual_amount' => $actualAmount];
        }

        $expectedAmount = (float) $payment->amount;

        if (abs($expectedAmount - $actualAmount) > 0.01) {
            $this->reconciliationExceptionRepository->create([
                'import_id' => $importId,
                'row_data' => $row,
                'expected_amount' => $expectedAmount,
                'actual_amount' => $actualAmount,
                'reason' => 'AMOUNT_MISMATCH',
                'status' => 'unresolved',
                'created_at' => now(),
            ]);

            return ['matched' => false, 'expected_amount' => $expectedAmount, 'actual_amount' => $actualAmount];
        }

        return ['matched' => true, 'expected_amount' => $expectedAmount, 'actual_amount' => $actualAmount];
    }

    private function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];

        if (count($lines) === 0) {
            return ['headers' => [], 'rows' => [], 'errors' => []];
        }

        $headers = array_values(array_filter(str_getcsv((string) array_shift($lines)), static fn ($header) => $header !== null && $header !== ''));
        $rows = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            if (count($values) !== count($headers)) {
                $errors[] = [
                    'row' => $index + 2,
                    'error' => 'MALFORMED_ROW',
                ];
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
    }
}
