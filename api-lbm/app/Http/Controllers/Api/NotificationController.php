<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\TeacherNotification;
use Illuminate\Http\Request;

/** Notifications (§4.1) — alerte l'enseignant d'un scan effectué en son nom par un tiers. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $enseignant = $this->authenticated($request);

        return response()->json(
            $enseignant->notifications()->orderByDesc('created_at')->get()
        );
    }

    public function markRead(Request $request, TeacherNotification $notification)
    {
        $enseignant = $this->authenticated($request);
        abort_unless($notification->enseignant_id === $enseignant->id, 403);

        $notification->update(['read_at' => now()]);

        return response()->json($notification);
    }

    private function authenticated(Request $request): Enseignant
    {
        $enseignant = $request->user();

        if (! $enseignant instanceof Enseignant) {
            abort(403, 'Authentification enseignant requise.');
        }

        return $enseignant;
    }
}
