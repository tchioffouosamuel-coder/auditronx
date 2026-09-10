#pragma once

// ---- Caméra OV5640 (ESP32-S3 + PSRAM requis) ----
// Brochage du module "ESP32-S3-CAM" générique le plus répandu (clones type
// Ai-Thinker/Freenove) — À VÉRIFIER/AJUSTER selon le schéma exact de votre
// carte, le brochage caméra varie beaucoup d'un fournisseur à l'autre.
#define CAMERA_PWDN_GPIO -1
#define CAMERA_RESET_GPIO -1
#define CAMERA_XCLK_GPIO 15
#define CAMERA_SIOD_GPIO 4
#define CAMERA_SIOC_GPIO 5
#define CAMERA_Y2_GPIO 11
#define CAMERA_Y3_GPIO 9
#define CAMERA_Y4_GPIO 8
#define CAMERA_Y5_GPIO 10
#define CAMERA_Y6_GPIO 12
#define CAMERA_Y7_GPIO 18
#define CAMERA_Y8_GPIO 17
#define CAMERA_Y9_GPIO 16
#define CAMERA_VSYNC_GPIO 6
#define CAMERA_HREF_GPIO 7
#define CAMERA_PCLK_GPIO 13
#define CAMERA_XCLK_FREQ_HZ 20000000

// QQVGA (160x120) est le point de départ stable pour l'ESP32-S3 + modèle
// de détection faciale embarquée : il réduit fortement le coût mémoire du
// décodage JPEG, sans sacrifier le nécessaire à la reconnaissance.
#define CAMERA_FRAME_SIZE FRAMESIZE_QQVGA
#define CAMERA_JPEG_QUALITY 20 // 0 (meilleure qualité) à 63 (plus compressé)

// ---- Nom BLE annoncé, auquel le téléphone de l'enseignant se connecte ----
// Le WiFi local (AP) a été remplacé par le BLE comme transport téléphone
// <-> borne : négociation bien plus rapide (pas de poignée de main WiFi ni de
// boîte de dialogue système), voir BLE_* ci-dessous.
inline constexpr char BLE_DEVICE_NAME[] = "AUDITRON-BORNE-01";

// UUIDs du service/caractéristiques BLE — DOIVENT correspondre exactement à
// ceux déclarés côté app mobile (mobile/lib/services/ble_service.dart).
inline constexpr char BLE_SERVICE_UUID[] = "b3a1a100-2c33-4e6f-9a1e-5f6a2e6c2b01";
inline constexpr char BLE_CHAR_SCAN_UUID[] = "b3a1a101-2c33-4e6f-9a1e-5f6a2e6c2b01";   // écriture : requête du téléphone
inline constexpr char BLE_CHAR_RESULT_UUID[] = "b3a1a102-2c33-4e6f-9a1e-5f6a2e6c2b01"; // lecture/notify : réponse de la borne

// ---- WiFi du modem/routeur qui fournit l'accès internet ----
// Uniquement en client (WIFI_STA) désormais : plus besoin du rôle AP
// puisque le téléphone parle en BLE, pas en WiFi local.
inline constexpr char STA_SSID[] = "AC-inGit";
inline constexpr char STA_PASSWORD[] = "12345678";

// Taille de la file d'attente locale. Bornée : au-delà, on refuse les
// nouveaux scans côté HTTP local plutôt que de saturer la carte SD. Volontairement
// plus petite qu'avant la caméra : chaque paquet pèse maintenant ~15-25 Ko
// (photo JPEG QVGA encodée en base64) contre moins d'1 Ko sans photo.
inline constexpr size_t MAX_QUEUE_SIZE = 40;

// Fichier (carte SD) où la file est persistée (survit à une coupure secteur :
// tant qu'un paquet n'a pas été confirmé par l'API, il reste sur la borne).
inline constexpr char QUEUE_FILE[] = "/queue.jsonl";

// ---- Carte micro-SD intégrée au PCB (ESP32-S3-WROOM CAM, ex. Freenove FNK0086) ----
// Bus SDMMC 1 bit (3 IOs) câblé en dur sur le PCB — ne pas modifier sauf si votre
// carte est un autre modèle. À vérifier sur la sérigraphie si les scans échouent.
#define SD_MMC_CMD_GPIO 38
#define SD_MMC_CLK_GPIO 39
#define SD_MMC_D0_GPIO 40

// ---- API distante ----
inline constexpr char API_BASE_URL[] = "https://api-ltm.auditronx.com/public";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md), puis codé en
// dur ici (pas de flux d'activation OTP pour ce device : il n'a pas
// d'écran ni d'utilisateur pour saisir un code).
inline constexpr char RELAY_API_TOKEN[] = "6|1WPc9xWbDurVjcjqlf00px5uuKvPZtrRuHYLnW1q92f6b31f";

// <= 100 (limite validée côté API) ; réduit à 5 : avec la photo, un lot de 50
// paquets pèserait ~1 Mo de JSON et dépasserait le tas ArduinoJson disponible.
inline constexpr size_t SYNC_BATCH_SIZE = 5;

// Capacité des documents ArduinoJson dynamiques (RAM/PSRAM) pour un paquet
// avec photo : JPEG QVGA qualité 15 ~= 8-15 Ko, base64 ~= x1.37 -> ~20 Ko,
// marge incluse pour le reste du paquet (token, payload, entêtes JSON).
inline constexpr size_t PACKET_JSON_CAPACITY = 32 * 1024;
inline constexpr size_t SYNC_BODY_JSON_CAPACITY = SYNC_BATCH_SIZE * PACKET_JSON_CAPACITY;

// Cadence de vérification de la connectivité / tentative de synchro.
inline constexpr uint32_t SYNC_INTERVAL_MS = 15000;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";

// ---- Reconnaissance faciale embarquée (ESP-WHO) — pointage 100% facial en
// principal, BLE/QR (ci-dessus) en secours si le visage n'est pas reconnu.
// Voir hardware/README.md § "Reconnaissance faciale embarquée".

// GPIO du buzzer piezo (bip de confirmation) — À CÂBLER/AJUSTER selon la
// carte, aucun GPIO libre documenté par défaut (même disclaimer que les pins
// caméra ci-dessus).
#define BUZZER_GPIO 21

// GPIO du signal HW201 : HIGH = présence d'un individu à traiter.
// La reconnaissance reste inactive tant que ce signal est bas ou qu'un scan
// QR est déjà en cours.
#define HW201_GPIO 14

// Score de similarité minimal (cosine, [0,1]) pour considérer un visage
// reconnu. Point de départ, PAS calibré : à ajuster sur le matériel réel
// (éclairage, angle/distance caméra) — voir hardware/README.md.
inline constexpr float RECOGNITION_THRESHOLD = 0.72f;

// Anti-doublon : un même enseignant reconnu deux fois dans cette fenêtre ne
// déclenche qu'un seul pointage (bip silencieux ensuite, le premier a déjà
// été mis en file).
inline constexpr uint32_t RECOGNITION_DEBOUNCE_MS = 5 * 60 * 1000;

// Fréquence de surveillance du trigger HW201. La reconnaissance elle-même
// reste déclenchée une seule fois par front montant.
inline constexpr uint32_t HW201_POLL_INTERVAL_MS = 10;
inline constexpr uint32_t HW201_DEBOUNCE_MS = 20;

// Délai conservé pour les opérations faciales lentes et les autres usages
// éventuels de la boucle de reconnaissance.
inline constexpr uint32_t RECOGNITION_LOOP_INTERVAL_MS = 10;

// Cadence de synchro du manifest (photos à enrôler + embeddings partagés).
inline constexpr uint32_t FACE_MANIFEST_SYNC_INTERVAL_MS = 60 * 1000;

inline constexpr char API_KIOSK_MANIFEST_PATH[] = "/api/kiosks/manifest";
inline constexpr char API_VISAGES_ENROLL_PATH[] = "/api/visages/enroll";

// Token Sanctum du device kiosk_facial, obtenu une fois via
// POST /api/devices/provision-kiosk (voir hardware/README.md), puis codé en
// dur ici — même principe que RELAY_API_TOKEN ci-dessus.
inline constexpr char KIOSK_API_TOKEN[] = "23|CwlY5IkxPX7wNVpnPrQ7QURkOvyeYuWmcFUpKale27f1cee5";

// Fichier SD où le cache local des visages enrôlés (id, nom, embedding) est
// persisté — survit à un reboot sans réseau (voir face_engine.h).
inline constexpr char FACE_CACHE_FILE[] = "/faces.jsonl";
