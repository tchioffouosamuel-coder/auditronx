<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * Envoi de notifications push (FCM) vers les téléphones enseignants. Best
 * effort : un push manqué (token expiré, app désinstallée, pas de
 * connectivité) ne doit jamais faire échouer l'action métier qui le
 * déclenche — on logue et on continue.
 */
class PushNotificationService
{
    public function sendToTeacher(int $enseignantId, string $title, string $body, array $data = []): void
    {
        $tokens = Device::where('teacher_id', $enseignantId)
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token');

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    private function send(string $fcmToken, string $title, string $body, array $data): void
    {
        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData(array_map(strval(...), $data));

            Firebase::messaging()->send($message);
        } catch (\Throwable $e) {
            Log::warning('push.send: échec envoi FCM', ['error' => $e->getMessage()]);
        }
    }
}
