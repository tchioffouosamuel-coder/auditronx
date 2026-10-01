<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\RelayPacketProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Passerelle offline (§hardware) : la borne ESP32-S3 (WIFI_AP_STA + caméra
 * OV5640) reçoit le pointage du téléphone en local (WiFi + HTTP), le met en
 * file sur flash, et — dès que sa connexion au modem est disponible — pousse
 * les paquets en attente ici par lots. Chaque paquet accusé de réception
 * (`ok`) peut être supprimé de sa file locale ; les autres (`retry`) y
 * restent pour une prochaine tentative.
 *
 * Authentification : le device relais (Device, device_type `relay_gateway`)
 * OU, depuis le pivot vers la reconnaissance faciale (§5), la borne kiosque
 * elle-même (device_type `kiosk_facial`) qui réutilise cette même file/moteur
 * de synchro pour ses paquets `facial_scan` — jamais le téléphone directement.
 * Pour un paquet `scan`/`admin_proxy`, l'identité de l'enseignant est résolue
 * à partir du token Sanctum que son app a émis au moment du scan
 * (`teacher_token`), exactement comme si la requête avait atteint l'API
 * directement : le relais ne fait que rejouer, en différé, une requête que le
 * téléphone avait déjà authentifiée. Un paquet `facial_scan` n'a pas de
 * `teacher_token` : l'enseignant reconnu est identifié par
 * `payload.enseignant_id`, la borne elle-même faisant office de point d'accès
 * (comme `AttendanceController::facialScan`, dont ce paquet rejoue la logique
 * via `AttendanceRecorder::recordFacialScan`).
 *
 * `payload.photo_base64` (optionnelle) : photo JPEG prise par la caméra de la
 * borne au moment du scan, encodée en base64 — preuve visuelle anti-fraude
 * (§hardware), décodée et stockée par AttendanceRecorder.
 *
 * Le traitement d'un paquet lui-même vit dans RelayPacketProcessor, partagé
 * avec l'import manuel du fichier queue.jsonl (RelayImportController).
 */
class RelaySyncController extends Controller
{
    public function __construct(private readonly RelayPacketProcessor $processor) {}

    /** POST /api/relay/sync */
    public function sync(Request $request)
    {
        $device = $request->user();

        if (! $device instanceof Device || $device->device_type !== 'relay_gateway' || $device->isRevoked()) {
            abort(403, 'Authentification passerelle relais requise.');
        }

        $data = $request->validate([
            'packets' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        // Validation paquet par paquet, pas sur tout le lot : un seul paquet
        // mal formé faisait échouer la requête entière en 422, la borne le
        // gardait en tête de file et le renvoyait indéfiniment, bloquant tous
        // les suivants. Un paquet invalide est désormais `rejected` (la borne
        // le purge par son local_id) et n'empêche plus le traitement des autres.
        $results = array_map(function ($packet) {
            $validator = Validator::make(is_array($packet) ? $packet : [], RelayPacketProcessor::rules());
            if ($validator->fails()) {
                return [
                    'local_id' => is_array($packet) && is_string($packet['local_id'] ?? null) ? $packet['local_id'] : null,
                    'status' => 'rejected',
                    'message' => $validator->errors()->first(),
                ];
            }

            return $this->processor->process($validator->validated());
        }, $data['packets']);

        return response()->json(['results' => $results]);
    }
}
