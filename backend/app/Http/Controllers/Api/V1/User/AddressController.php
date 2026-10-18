<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderBy('is_default', 'desc')->get();
        return response()->json(['success' => true, 'data' => $addresses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'apartment' => 'nullable|string|max:255',
            'entrance' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            'intercom' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'is_default' => 'boolean',
            'region_id' => 'nullable|exists:regions,id',
            'district_id' => 'nullable|exists:districts,id',
        ]);

        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($data);

        return response()->json(['success' => true, 'data' => $address]);
    }

    public function update(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        
        $data = $request->validate([
            'title' => 'string|max:255',
            'address' => 'string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'apartment' => 'nullable|string|max:255',
            'entrance' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            'intercom' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'is_default' => 'boolean',
            'region_id' => 'nullable|exists:regions,id',
            'district_id' => 'nullable|exists:districts,id',
        ]);

        if (isset($data['is_default']) && $data['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json(['success' => true, 'data' => $address]);
    }

    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json(['success' => true, 'message' => 'Address deleted']);
    }
}
