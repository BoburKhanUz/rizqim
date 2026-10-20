<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Region;
use App\Models\District;

#[Group('Locations', "Viloyat va tumanlar.")]
class LocationController extends Controller
{
    use ApiResponse;

    /**
     * Regions and districts
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{regions: list<array{id: int, name: string, is_active: bool}>, districts: list<array{id: int, region_id: int, name: string, is_active: bool}>}}')]
    public function index(): JsonResponse
    {
        // Return all regions and districts sorted by name
        $regions = Region::orderBy('name_uz')->get(['id', 'name_uz as name', 'is_active']);
        $districts = District::orderBy('name_uz')->get(['id', 'region_id', 'name_uz as name', 'is_active']);

        return $this->success([
            'regions' => $regions,
            'districts' => $districts,
        ], 'Locations retrieved successfully.');
    }
}
