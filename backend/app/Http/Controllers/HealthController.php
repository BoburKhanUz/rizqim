<?php

namespace App\Http\Controllers;

use App\Support\HealthReport;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthReport $health): JsonResponse
    {
        $report = $health->report();

        return response()->json($report, $report['status'] === 'fail' ? 503 : 200, [
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
