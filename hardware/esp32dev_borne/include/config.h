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
// Limite de sécurité de la file. La carte SD permet de conserver beaucoup
// plus de scans hors ligne ; LittleFS conserve une limite basse lorsqu'elle
// sert de secours.
inline constexpr size_t MAX_QUEUE_SIZE_SD = 2000;
inline constexpr size_t MAX_QUEUE_SIZE_LITTLEFS = 70;

// ---- Protocole photo BLE (§anti-procuration) ----
// `scanChar` reçoit désormais plusieurs écritures GATT préfixées d'un octet
// de tag — DOIT correspondre exactement à mobile/lib/services/ble_service.dart.
// 0x01 = chunk photo (JPEG brut, pas de base64 : encoder avant l'envoi
// gonflerait le volume transmis sur l'air d'environ 33% pour rien — la borne
// encode elle-même juste avant l'injection dans le paquet JSON, comme
// esp32_borne/ le fait déjà pour sa propre caméra). 0x03 = chunk JSON
// intermédiaire (le JSON final peut lui aussi dépasser un seul chunk : un MTU
// négocié bas — 255o constaté sur un Itel bas de gamme, chipset MediaTek/
// Unisoc — peut être trop court pour un JSON avec un long token/qr_code).
// 0x02 = dernier morceau du JSON (chunk final, éventuellement vide si le JSON
// tenait dans une seule écriture — comportement historique) : à sa réception,
// la borne assemble le JSON complet et associe les chunks photo déjà reçus à
// ce scan avant de répondre.
inline constexpr uint8_t BLE_TAG_PHOTO_CHUNK = 0x01;
inline constexpr uint8_t BLE_TAG_SCAN_FINAL = 0x02;
inline constexpr uint8_t BLE_TAG_JSON_CHUNK = 0x03;

// Plafond du JSON brut accumulé par chunks avant le tag final : bien au-delà
// d'un scan normal (qr_code/token/motif tiennent large sous 1 Ko), protection
// contre un buffer non borné en cas de bug/version incompatible de l'app.
inline constexpr size_t MAX_JSON_CHUNK_BYTES = 4 * 1024;

// Plafond du selfie brut (avant base64) accepté par scan. Le téléphone vise
// ~160x120 JPEG qualité ~20 (quelques Ko) — ce plafond n'est qu'une
// protection contre un buffer non borné côté borne en cas de bug/version
// incompatible de l'app, pas un objectif de taille normal.
inline constexpr size_t MAX_PHOTO_BYTES = 24 * 1024;

// Fichier où la file est persistée (survit à une coupure secteur : tant qu'un
// paquet n'a pas été confirmé par l'API, il reste sur la borne). La carte
// micro-SD est utilisée si elle est détectée ; LittleFS sert de secours.
inline constexpr char QUEUE_FILE[] = "/queue.jsonl";

// Lecteur micro-SD SPI pour ESP32 DevKit classique. Adapter ces broches au
// module SD utilisé ; ce sont les broches VSPI usuelles (SCK/MISO/MOSI/CS).
inline constexpr uint8_t SD_SCK_GPIO = 18;
inline constexpr uint8_t SD_MISO_GPIO = 19;
inline constexpr uint8_t SD_MOSI_GPIO = 23;
inline constexpr uint8_t SD_CS_GPIO = 5;

// Buzzer actif : bip court à la réception complète d'un scan BLE.
inline constexpr uint8_t BUZZER_GPIO = 25;

// ---- API distante ----
inline constexpr char API_BASE_URL[] = "https://api-ltm.auditronx.com/public";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md) — CE module
// doit avoir son propre token, distinct de celui d'esp32_borne/ (chaque
// device relais est identifié individuellement côté API).
inline constexpr char RELAY_API_TOKEN[] = "48|5ECwvUBQDs2MQWRw0xDq9KcemNdRLXEN23PqbD9E81d85a35";

// <= 100 (limite validée côté API). Réduit depuis l'ajout du selfie (chaque
// paquet est maintenant nettement plus lourd, voir PACKET_JSON_CAPACITY) —
// reste néanmoins plus généreux que sur esp32_borne/ (5) : cet ESP32 n'a pas
// de PSRAM, mais la photo du téléphone (~3-6 Ko) est bien plus légère que
// celle de la caméra OV5640 QVGA (~8-15 Ko) qu'esp32_borne/ doit encaisser.
inline constexpr size_t SYNC_BATCH_SIZE = 8;

// Capacité des documents ArduinoJson dynamiques (RAM) par paquet : JPEG
// ~160x120 qualité ~20 (quelques Ko) encodé en base64 (x1.37) + payload
// qr_code/enseignant_id/motif + token + entêtes JSON, avec marge.
inline constexpr size_t PACKET_JSON_CAPACITY = 10 * 1024;
inline constexpr size_t SYNC_BODY_JSON_CAPACITY = SYNC_BATCH_SIZE * PACKET_JSON_CAPACITY;

// Cadence de vérification de la connectivité / tentative de synchro.
inline constexpr uint32_t SYNC_INTERVAL_MS = 15000;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";