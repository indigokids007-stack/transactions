<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportRequest;
use App\Reports\SummaryReport;
use App\Reports\TrendReport;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly SummaryReport $summaryReport,
        private readonly TrendReport $trendReport,
    ) {}

    public function summary(ReportRequest $request): JsonResponse
    {
        return response()->json(
            $this->summaryReport->build($request->actor(), $request->filters(), $request->grouping())
        );
    }

    public function trend(ReportRequest $request): JsonResponse
    {
        return response()->json(
            $this->trendReport->build($request->actor(), $request->filters(), $request->trendInterval())
        );
    }
}
