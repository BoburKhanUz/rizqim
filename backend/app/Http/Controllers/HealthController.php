<?php

namespace App\Http\Controllers;

use App\Support\HealthReport;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

#[Group('Health')]
class HealthController extends Controller
{
    /**
     * Health check
     *
     * 200 when everything works (`ok`) or works with warnings (`degraded`), 503 when a component fails. Each check
     * carries only its `status`, unless the request comes from the internal network or sends `X-Health-Token`.
     */
    #[ApiDocResponse(200, type: "array{status: 'ok'|'degraded', checks: array<string, array{status: string}>}")]
    #[ApiDocResponse(503, 'A component fails', type: "array{status: 'fail', checks: array<string, array{status: string}>}")]
    public function __invoke(Request $request, HealthReport $health): JsonResponse
    {
        $report = $health->report();
        $status = $report['status'] === 'fail' ? 503 : 200;

        // Same status code for everyone (the Docker healthchecks and uptime monitors read only that); the numbers
        // (latency, queue backlog, heartbeat age) only for the internal network or a request with the health token
        return response()->json($this->maySeeDetails($request) ? $report : HealthReport::summarize($report), $status, [
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function maySeeDetails(Request $request): bool
    {
        $token = (string) config('health.token');
        $sent = $request->header('X-Health-Token');
        if ($token !== '' && is_string($sent) && hash_equals($token, $sent)) {
            return true;
        }

        // ip() is the real client behind the trusted proxies (X-Forwarded-For), not the proxy hop itself
        $ip = $request->ip();

        return $ip !== null && IpUtils::checkIp($ip, (array) config('health.internal_networks'));
    }
}
