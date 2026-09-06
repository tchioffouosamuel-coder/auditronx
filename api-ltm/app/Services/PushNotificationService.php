<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
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

    /**
     * Envoi à un token FCM connu directement (pas de Device : cas de la
     * livraison d'OTP par notification, §otp-approval — à ce stade
     * l'enseignant n'a pas encore de device Sanctum/activé, donc pas de ligne
     * `devices` où chercher un token).
     */
    public function sendToToken(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $this->send($fcmToken, $title, $body, $data);
    }

    /**
     * Notification de validation OTP (§otp-approval) envoyée à tous les admins
     * connectés au backoffice. Message *data-only* (sans bloc `notification`) :
     * ça laisse le service worker web construire lui-même la notification avec
     * les actions Valider/Refuser plutôt que de subir l'affichage par défaut du
     * navigateur, qui ne supporte pas de boutons d'action sur un message FCM
     * "notification" classique.
     */
    public function sendToAdmins(string $title, string $body, array $data = []): void
    {
        $tokens = User::whereNotNull('fcm_token')->pluck('fcm_token');

        foreach ($tokens as $token) {
            $this->sendDataOnly($token, $title, $body, $data);
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

    private function sendDataOnly(string $fcmToken, string $title, string $body, array $data): void
    {
        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withData(array_map(strval(...), [...$data, 'title' => $title, 'body' => $body]));

            Firebase::messaging()->send($message);
        } catch (\Throwable $e) {
            Log::warning('push.send: échec envoi FCM (admin)', ['error' => $e->getMessage()]);
        }
    }
}
