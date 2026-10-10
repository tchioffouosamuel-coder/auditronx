<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Administration des appareils (§4.2) — points QR. */
class QrPointController extends Controller
{
    public function index()
    {
        return response()->json(QrPoint::orderBy('label')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $data['code'] = (string) Str::uuid();

        return response()->json(QrPoint::create($data), 201);
    }

    public function update(Request $request, QrPoint $qrPoint)
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:255']]);

        $qrPoint->update($data);

        return response()->json($qrPoint);
    }

    public function destroy(QrPoint $qrPoint)
    {
        $qrPoint->delete();

        return response()->json(status: 204);
    }
}
