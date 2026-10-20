<?php

namespace App\Http\Controllers\Api\V1;

use App\Features\Features;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Feature flags', "Restoran bo'yicha feature flaglar (Superadmin > Feature flaglar).")]
class FeatureController extends Controller
{
    /**
     * Feature flags
     *
     * Flags (Laravel Pennant) as the signed-in user's store sees them: a map of flag name to on/off. Clients treat a
     * missing flag as off.
     */
    #[ApiDocResponse(200, type: 'array{success: true, data: array{features: array<string, bool>}}')]
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'features' => Features::forStore($request->user()?->store),
            ],
        ]);
    }
}
