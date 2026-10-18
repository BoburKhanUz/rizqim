<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Region;
use App\Models\District;

class LocationController extends Controller
{
    use ApiResponse;

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
