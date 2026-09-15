#pragma once

// ---- Nom BLE annoncé, auquel le téléphone de l'enseignant se connecte ----
// Cosmétique : l'app mobile découvre la borne par BLE_SERVICE_UUID, pas par
// ce nom (voir mobile/lib/services/ble_service.dart) — changez-le librement,
// utile surtout pour distinguer plusieurs bornes au moniteur série.
inline constexpr char BLE_DEVICE_NAME[] = "AUDITRON-BORNE-02";

// UUIDs du service/caractéristiques BLE — DOIVENT correspondre exactement à
// ceux déclarés côté app mobile (mobile/lib/services/ble_service.dart), donc
// identiques à esp32_borne/include/config.h.
inline constexpr char BLE_SERVICE_UUID[] = "b3a1a100-2c33-4e6f-9a1e-5f6a2e6c2b01";
inline constexpr char BLE_CHAR_SCAN_UUID[] = "b3a1a101-2c33-4e6f-9a1e-5f6a2e6c2b01";   // écriture : requête du téléphone
inline constexpr char BLE_CHAR_RESULT_UUID[] = "b3a1a102-2c33-4e6f-9a1e-5f6a2e6c2b01"; // lecture/notify : réponse de la borne

// ---- WiFi du modem/routeur qui fournit l'accès internet ----
// Uniquement en client (WIFI_STA) : le téléphone parle en BLE, pas en WiFi local.
// inline constexpr char STA_SSID[] = "Galaxy S22 4D30";
// inline constexpr char STA_PASSWORD[] = "19750000";
inline constexpr char STA_SSID[] = "AC-inGit";
inline constexpr char STA_PASSWORD[] = "12345678";
// Taille de la file d'attente locale. Bornée : au-delà, on refuse les
// nouveaux scans côté HTTP local plutôt que de saturer la flash. Peut être
// bien plus grande que sur esp32_borne/ : sans photo, chaque paquet pèse
// quelques centaines d'octets contre ~15-25 Ko avec la photo.
inline constexpr size_t MAX_QUEUE_SIZE = 200;

// Fichier (LittleFS) où la file est persistée (survit à une coupure secteur :
// tant qu'un paquet n'a pas été confirmé par l'API, il reste sur la borne).
// Pas de carte micro-SD sur un ESP32 DevKit générique — LittleFS (flash
// interne) remplace SD_MMC ici, même logique d'écriture/relecture ligne par
// ligne (voir esp32_borne/src/main.cpp).
inline constexpr char QUEUE_FILE[] = "/queue.jsonl";

// ---- API distante ----
inline constexpr char API_BASE_URL[] = "https://api-ltm.auditronx.com/public";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md) — CE module
// doit avoir son propre token, distinct de celui d'esp32_borne/ (chaque
// device relais est identifié individuellement côté API).
inline constexpr char RELAY_API_TOKEN[] = "48|5ECwvUBQDs2MQWRw0xDq9KcemNdRLXEN23PqbD9E81d85a35";

// <= 100 (limite validée côté API). Sans photo, un lot plus généreux reste
// largement sous le tas ArduinoJson disponible (contrairement à esp32_borne/).
inline constexpr size_t SYNC_BATCH_SIZE = 20;

// Capacité des documents ArduinoJson dynamiques (RAM) pour un paquet sans
// photo : payload qr_code/enseignant_id/motif + token + entêtes JSON tient
// très large sous 4 Ko.
inline constexpr size_t PACKET_JSON_CAPACITY = 4 * 1024;
inline constexpr size_t SYNC_BODY_JSON_CAPACITY = SYNC_BATCH_SIZE * PACKET_JSON_CAPACITY;

// Cadence de vérification de la connectivité / tentative de synchro.
inline constexpr uint32_t SYNC_INTERVAL_MS = 15000;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";