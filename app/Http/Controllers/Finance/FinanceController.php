<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceQueryRequest;
use App\Services\Finance\GetFinanceDashboardService;
use App\Services\Finance\PrepareFinanceExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinanceController extends Controller
{
    public function __construct(private readonly GetFinanceDashboardService $dashboard, private readonly PrepareFinanceExportService $exports) {}

    public function tenant(FinanceQueryRequest $request): Response
    {
        return Inertia::render('tenant/finance', $this->dashboard->tenant($request->identity(), $request->validated('from'), $request->validated('to'), $request->validated('outlet')));
    }

    public function driver(FinanceQueryRequest $request): Response
    {
        return Inertia::render('driver/finance', $this->dashboard->driver($request->identity(), $request->validated('from'), $request->validated('to')));
    }

    public function support(FinanceQueryRequest $request): Response
    {
        return Inertia::render('super-user/finance', $this->dashboard->support($request->identity(), $request->validated('from'), $request->validated('to')));
    }

    public function tenantExport(FinanceQueryRequest $request): StreamedResponse
    {
        return $this->stream($this->exports->tenant($request->identity(), $request->validated('from'), $request->validated('to'), $request->validated('outlet')));
    }

    public function driverExport(FinanceQueryRequest $request): StreamedResponse
    {
        return $this->stream($this->exports->driver($request->identity(), $request->validated('from'), $request->validated('to')));
    }

    public function supportExport(FinanceQueryRequest $request): StreamedResponse
    {
        return $this->stream($this->exports->support($request->identity(), $request->validated('from'), $request->validated('to')));
    }

    /** @param array{filename: string, headers: list<string>, rows: iterable<int, list<int|string|null>>} $export */
    private function stream(array $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $export['headers']);
            foreach ($export['rows'] as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
