<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\DTOs\ReportFilterData;
use Modules\Finance\Application\UseCases\GenerateFinanceReportQuery;
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;

class FinanceReportController extends Controller
{
    public function __construct(
        private readonly GenerateFinanceReportQuery $reportQuery,
    ) {}

    public function show(Request $request, string $type): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        if (! in_array($type, ReportFilterData::VALID_TYPES, true)) {
            abort(422, 'Tipo de informe no válido.');
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'group_by' => ['nullable', 'in:' . implode(',', ReportFilterData::VALID_GROUP_BY)],
        ]);

        $validated['type'] = $type;

        $report = $this->reportQuery->execute(
            (int) Auth::user()->clinic_id,
            ReportFilterData::fromRequest($validated),
        );

        return response()->json(['data' => $report]);
    }

    public function export(Request $request, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        if (! in_array($type, ReportFilterData::VALID_TYPES, true)) {
            abort(422, 'Tipo de informe no válido.');
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'group_by' => ['nullable', 'in:' . implode(',', ReportFilterData::VALID_GROUP_BY)],
        ]);

        $validated['type'] = $type;

        $report = $this->reportQuery->execute(
            (int) Auth::user()->clinic_id,
            ReportFilterData::fromRequest($validated),
        );

        $filename = "informe_{$type}_" . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Headers
            fputcsv($handle, $report['headers'], ';');

            // Rows
            foreach ($report['rows'] as $row) {
                fputcsv($handle, $row, ';');
            }

            // Blank line + summary
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Resumen'], ';');

            foreach ($report['summary'] as $key => $value) {
                $label = match ($key) {
                    'total' => 'Total',
                    'count' => 'Nº registros',
                    'total_revenue' => 'Total ingresos',
                    'total_expenses' => 'Total gastos',
                    'total_profit' => 'Beneficio total',
                    'margin_percentage' => 'Margen %',
                    'total_labor' => 'Coste laboral total',
                    'total_contribution' => 'Contribución total',
                    'total_count' => 'Nº citas totales',
                    'avg_ticket' => 'Ticket medio',
                    default => $key,
                };

                fputcsv($handle, [$label, $value], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
