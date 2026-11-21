<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Domain\Pos\Releases\PosAppReleases;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): the public list of POS app releases for the download site
 * (pos.rizqim.uz). No token; throttled per address (`pos-releases`); the answer is cached 5 minutes server side and
 * for the same time by clients and proxies. Nothing here is a store's data and nothing internal (no ids) is shown.
 */
#[Group('Terminal', "POS/KDS terminali: ulash va xodimning PIN sessiyasi. Ulashdan keyingi so'rovlar terminal tokeni bilan.")]
class PosReleaseManifestController extends Controller
{
    use ApiResponse;

    /**
     * POS app releases (public)
     *
     * Every non-withdrawn release of the four POS apps: `app` (cashier, waiter, kds, customer_display), `platform`
     * (android, windows), `version`, `build`, `file_url`, `sha256`, `size_bytes`, `notes` (Uzbek), `released_at`,
     * `is_minimum`, `minimum_from` (the day the notice turns red), `is_latest` (the highest version of its app and
     * platform). Sorted by app, platform, then newest first. Empty while nothing was published. No auth, cached 5
     * minutes, at most 30 requests a minute per address (429 after).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{releases: list<array{app: string, platform: string, version: string, build: int, file_url: string, sha256: string, size_bytes: int, notes: string|null, released_at: string, is_minimum: bool, minimum_from: string|null, is_latest: bool}>}}')]
    public function index(): JsonResponse
    {
        return $this->success(['releases' => app(PosAppReleases::class)->manifest()])
            ->header('Cache-Control', 'public, max-age=' . PosAppReleases::MANIFEST_TTL);
    }
}
