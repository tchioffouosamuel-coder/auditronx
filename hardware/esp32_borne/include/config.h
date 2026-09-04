#pragma once

// ---- Point d'accès local auquel le téléphone de l'enseignant se connecte ----
inline constexpr char AP_SSID[] = "AUDITRON-BORNE-01";
inline constexpr char AP_PASSWORD[] = "change-me-wifi-pass"; // 8+ caractères, WPA2

// ---- WiFi du modem/routeur qui fournit l'accès internet ----
// L'ESP32 tient les deux rôles à la fois (WIFI_AP_STA) : une fois connecté
// au modem en STA, le SDK aligne automatiquement le canal du softAP sur
// celui du STA — inutile de le fixer nous-mêmes comme dans l'ancienne
// architecture à deux modules (où ESP1 et ESP2 devaient partager un canal
// ESP-NOW commun).
inline constexpr char STA_SSID[] = "MODEM-WIFI-SSID";
inline constexpr char STA_PASSWORD[] = "modem-wifi-password";

// Taille de la file d'attente locale. Bornée : au-delà, on refuse les
// nouveaux scans côté HTTP local plutôt que de saturer LittleFS.
inline constexpr size_t MAX_QUEUE_SIZE = 200;

// Fichier LittleFS où la file est persistée (survit à une coupure secteur :
// tant qu'un paquet n'a pas été confirmé par l'API, il reste sur la borne).
inline constexpr char QUEUE_FILE[] = "/queue.jsonl";

// ---- API distante ----
inline constexpr char API_BASE_URL[] = "https://votre-domaine.example.com";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md), puis codé en
// dur ici (pas de flux d'activation OTP pour ce device : il n'a pas
// d'écran ni d'utilisateur pour saisir un code).
inline constexpr char RELAY_API_TOKEN[] = "REPLACE_WITH_TOKEN_FROM_PROVISION_RELAY";

inline constexpr size_t SYNC_BATCH_SIZE = 50; // <= 100 (limite validée côté API)

// Cadence de vérification de la connectivité / tentative de synchro.
inline constexpr uint32_t SYNC_INTERVAL_MS = 15000;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";
