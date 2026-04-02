<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ReportService
{
    public function appointments(User $actor, array $filters): \Symfony\Component\HttpFoundation\Response
    {
        $query = Appointment::query()->with(['provider']);

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', $filters['to']);
        }

        if (! empty($filters['site_id']) && $actor->role === 'administrator') {
            $query->where('site_id', (int) $filters['site_id']);
        }

        $rows = $query->orderBy('start_time')->get()->map(function (Appointment $item): array {
            return [
                'appointment_id' => $item->id,
                'client_id' => $item->client_id,
                'provider' => $item->provider?->identifier,
                'service_type' => $item->service_type,
                'scheduled_time' => optional($item->start_time)->toIso8601String(),
                'status' => $item->status,
                'site' => $item->site_id,
            ];
        })->all();

        return $this->renderRows($rows, 'appointments_report', $filters['format'] ?? 'csv');
    }

    public function financial(User $actor, array $filters): \Symfony\Component\HttpFoundation\Response
    {
        $paymentQuery = Payment::query();
        if ($actor->role !== 'administrator') {
            $paymentQuery->where('site_id', $actor->site_id);
        } elseif (! empty($filters['site_id'])) {
            $paymentQuery->where('site_id', (int) $filters['site_id']);
        }
        if (! empty($filters['from'])) {
            $paymentQuery->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $paymentQuery->where('created_at', '<=', $filters['to']);
        }
        $payments = $paymentQuery->get()->map(function (Payment $payment): array {
            return [
                'date' => optional($payment->created_at)->toDateString(),
                'payment_reference' => $payment->reference_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'fee_type' => null,
                'client_id' => $payment->client_id ?? $payment->posted_by,
                'site' => $payment->site_id,
            ];
        })->all();

        $feeQuery = $this->buildFeeQuery($actor);
        if (! empty($filters['site_id']) && $actor->role === 'administrator') {
            $feeQuery->whereHas('client', function (Builder $query) use ($filters): void {
                $query->where('site_id', (int) $filters['site_id']);
            });
        }
        if (! empty($filters['from'])) {
            $feeQuery->where('assessed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $feeQuery->where('assessed_at', '<=', $filters['to']);
        }
        $fees = $feeQuery->get()->map(function (FeeAssessment $fee): array {
            return [
                'date' => optional($fee->assessed_at)->toDateString(),
                'payment_reference' => 'FEE-'.$fee->id,
                'amount' => $fee->amount,
                'method' => $fee->status,
                'fee_type' => $fee->fee_type,
                'client_id' => $fee->client_id,
                'site' => $fee->client?->site_id,
            ];
        })->all();

        $rows = array_values(array_merge($payments, $fees));

        return $this->renderRows($rows, 'financial_report', $filters['format'] ?? 'csv');
    }

    private function buildFeeQuery(User $actor): Builder
    {
        $query = FeeAssessment::query()
            ->with('client')
            ->select(['id', 'fee_type', 'amount', 'status', 'assessed_at', 'client_id']);

        if ($actor->role !== 'administrator') {
            $query->whereHas('client', function (Builder $siteQuery) use ($actor): void {
                $siteQuery->where('site_id', $actor->site_id);
            });
        }

        return $query;
    }

    public function audit(User $actor, array $filters): \Symfony\Component\HttpFoundation\Response
    {
        $rows = AuditLog::query()->with('user:id,identifier')->orderBy('created_at')->get()->map(function (AuditLog $log): array {
            return [
                'timestamp' => optional($log->created_at)->toIso8601String(),
                'actor' => $log->user?->identifier,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'ip_address' => $log->ip_address,
            ];
        })->all();

        return $this->renderRows($rows, 'audit_report', $filters['format'] ?? 'csv');
    }

    private function renderRows(array $rows, string $filenameBase, string $format): \Symfony\Component\HttpFoundation\Response
    {
        $format = strtolower($format);

        if ($format === 'json') {
            return Response::json(['success' => true, 'data' => $rows]);
        }

        if ($format === 'xlsx') {
            return $this->toXlsx($rows, $filenameBase);
        }

        return Response::make($this->toCsv($rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filenameBase.'.csv"',
        ]);
    }

    private function toXlsx(array $rows, string $filenameBase): \Symfony\Component\HttpFoundation\Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if (! empty($rows)) {
            $headers = array_keys($rows[0]);
            foreach ($headers as $colIndex => $header) {
                $column = Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($column.'1', $header);
            }

            foreach ($rows as $rowIndex => $row) {
                foreach ($headers as $colIndex => $header) {
                    $column = Coordinate::stringFromColumnIndex($colIndex + 1);
                    $sheet->setCellValue($column.($rowIndex + 2), $row[$header] ?? '');
                }
            }
        }

        $writer = new XlsxWriter($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();

        return Response::make($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filenameBase.'.xlsx"',
        ]);
    }

    private function toCsv(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $headers = array_keys($rows[0]);
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = (string) ($row[$header] ?? '');
                $escaped = '"'.str_replace('"', '""', $value).'"';
                $line[] = $escaped;
            }
            $lines[] = implode(',', $line);
        }

        return implode("\n", $lines)."\n";
    }
}
