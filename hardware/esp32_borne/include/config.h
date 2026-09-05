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

// QVGA (320x240) suffit pour une photo d'identification, et garde chaque
// paquet raisonnable (JSON + base64 + file d'attente flash + bande passante
// de synchro) malgré le capteur 5 Mpx de l'OV5640, capable de bien plus.
#define CAMERA_FRAME_SIZE FRAMESIZE_QVGA
#define CAMERA_JPEG_QUALITY 15 // 0 (meilleure qualité) à 63 (plus compressé)

// ---- Point d'accès local auquel le téléphone de l'enseignant se connecte ----
inline constexpr char AP_SSID[] = "AUDITRON-BORNE-01";
inline constexpr char AP_PASSWORD[] = "change-me-wifi-pass"; // 8+ caractères, WPA2

// ---- WiFi du modem/routeur qui fournit l'accès internet ----
// L'ESP32 tient les deux rôles à la fois (WIFI_AP_STA) : une fois connecté
// au modem en STA, le SDK aligne automatiquement le canal du softAP sur
// celui du STA — inutile de le fixer nous-mêmes comme dans l'ancienne
// architecture à deux modules (où ESP1 et ESP2 devaient partager un canal
// ESP-NOW commun).
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
inline constexpr char API_BASE_URL[] = "https://votre-domaine.example.com";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md), puis codé en
// dur ici (pas de flux d'activation OTP pour ce device : il n'a pas
// d'écran ni d'utilisateur pour saisir un code).
inline constexpr char RELAY_API_TOKEN[] = "REPLACE_WITH_TOKEN_FROM_PROVISION_RELAY";

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
