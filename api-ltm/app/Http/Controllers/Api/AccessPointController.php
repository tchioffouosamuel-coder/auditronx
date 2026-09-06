<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessPoint;
use Illuminate\Http\Request;

/** Administration des appareils (§4.2) — bornes WiFi autorisées. */
class AccessPointController extends Controller
{
    public function index()
    {
        return response()->json(AccessPoint::orderBy('label')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bssid' => ['required', 'string', 'max:50', 'unique:access_points,bssid'],
            'ssid' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(AccessPoint::create($data), 201);
    }

    public function update(Request $request, AccessPoint $accessPoint)
    {
        $data = $request->validate([
            'bssid' => ['sometimes', 'string', 'max:50', 'unique:access_points,bssid,'.$accessPoint->id],
            'ssid' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $accessPoint->update($data);

        return response()->json($accessPoint);
    }

    public function destroy(AccessPoint $accessPoint)
    {
        $accessPoint->delete();

        return response()->json(status: 204);
    }
}
