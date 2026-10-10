/**
 * ESP32 générique "borne" — serveur BLE local pour le téléphone de
 * l'enseignant ET client WiFi (STA) connecté au modem pour la synchro API.
 * Module ESP32 DevKit générique, pas nécessairement colocalisé avec le
 * modem.
 *
 * Le téléphone parle en BLE (pas WiFi local : négociation trop lente, voir
 * §hardware) — reçoit le pointage via une caractéristique BLE, l'écrit
 * immédiatement sur la micro-SD si elle est disponible (LittleFS en secours)
 * avant toute tentative réseau, puis un
 * moteur de pull périodique le pousse vers l'API dès qu'internet est
 * disponible. Un paquet n'est retiré de la file locale que sur confirmation
 * explicite de l'API (`ok` ou `rejected`) — jamais avant, pour ne rien perdre
 * en cas de coupure secteur ou réseau.
 */
#include <Arduino.h>
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <FS.h>
#include <LittleFS.h>
#include <SD.h>
#include <SPI.h>
#include <ArduinoJson.h>
#include <time.h>
#include <vector>
#include <deque>
#include <algorithm>
#include <mbedtls/base64.h>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>
#include <freertos/semphr.h>
#include <NimBLEDevice.h>
#include <esp_system.h>
#include <Update.h>
#include <Preferences.h>
#include <mbedtls/md.h>
#include <mbedtls/ssl.h>
#include <esp_ota_ops.h>
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// ===== Configuration (contenu de include/config.h) =====

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
inline constexpr char STA_SSID[] = "Auditron";
inline constexpr char STA_PASSWORD[] = "1234567890";
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
inline constexpr uint32_t SD_SPI_FREQUENCY_HZ = 10000000;

// Buzzer actif : bip court à la réception complète d'un scan BLE.
inline constexpr uint8_t BUZZER_GPIO = 25;

// ---- API distante ----
inline constexpr char API_BASE_URL[] = "https://api-ltm.auditronx.com/public";
inline constexpr char API_RELAY_SYNC_PATH[] = "/api/relay/sync";

// Token Sanctum du device relay_gateway, obtenu une fois via
// POST /api/devices/provision-relay (voir hardware/README.md) — CE module
// doit avoir son propre token, distinct de celui d'esp32_borne/ (chaque
// device relais est identifié individuellement côté API).
inline constexpr char RELAY_API_TOKEN[] = "76|nJRGTR5elU6Cg6kqXpYexMnZMKlVMTNrOLJAY8wfc653073d";

// Plus de capacité fixe de document JSON par paquet : les documents sont
// dimensionnés sur le contenu réel et le selfie est écrit dans la file par
// blocs, sans passer par un document (voir processScan/appendToQueue dans
// main.cpp). L'ancien bloc fixe de 20 Ko d'un seul tenant n'était plus
// allouable dès que BLE et TLS avaient morcelé le tas.

// Cadence de synchro : un paquet par requête (la négociation TLS consomme
// déjà l'essentiel de la RAM libre sur cet ESP32 sans PSRAM), toutes les 3 s
// tant que la file n'est pas vide. File vide : simple lecture de la file,
// aucune connexion réseau. Après un échec (pas d'internet, API injoignable),
// la tentative suivante attend SYNC_RETRY_INTERVAL_MS.
inline constexpr uint32_t SYNC_INTERVAL_MS = 3000;
inline constexpr uint32_t SYNC_RETRY_INTERVAL_MS = 5000;

// Auto-test de connectivité lancé quand l'API ne répond pas (voir
// diagnoseConnectivity() dans main.cpp) : au premier échec, puis au plus
// toutes les NET_DIAG_INTERVAL_MS tant que les échecs durent.
inline constexpr char NET_DIAG_HTTPS_URL[] = "https://www.google.com/generate_204";
// Page volumineuse servie SANS TLS (~80 Ko) : teste la réception de trames pleines.
inline constexpr char NET_DIAG_HTTP_LARGE_URL[] = "http://www.google.com/";
inline constexpr size_t NET_DIAG_LARGE_BYTES = 20000;
inline constexpr uint32_t NET_DIAG_INTERVAL_MS = 10UL * 60UL * 1000UL;

// ---- Délais des connexions HTTPS vers l'API ----
// Courts à dessein : une tentative qui n'aboutit pas doit libérer vite la
// tâche de synchro pour réessayer (SYNC_RETRY_INTERVAL_MS), au lieu de la
// bloquer 120 s (délai de poignée de main par défaut du core).
inline constexpr int32_t HTTPS_CONNECT_TIMEOUT_MS = 8000;
inline constexpr unsigned long HTTPS_HANDSHAKE_TIMEOUT_S = 20;

// ---- Mémoire réservée au TLS (voir reserveTlsMemory() dans main.cpp) ----
// Deux tampons d'enregistrement mbedTLS de ~16,7 Ko + la poignée de main.
inline constexpr size_t TLS_RESERVE_BLOCK_SIZES[] = {17 * 1024, 17 * 1024, 10 * 1024};
// Échecs TLS consécutifs "faute de mémoire" avant un redémarrage de secours.
inline constexpr uint8_t TLS_MEMORY_FAILURES_BEFORE_RESTART = 6;
// Cadence de la ligne de suivi "[mem] ..." (moniteur série et backoffice).
inline constexpr uint32_t MEMORY_LOG_INTERVAL_MS = 10UL * 60UL * 1000UL;

// ---- Moniteur série à distance (backoffice > Moniteur des bornes) ----
// Chaque ligne imprimée sur le port série est aussi gardée dans un tampon RAM
// et poussée vers POST /api/relay/logs. Sans internet, les lignes les plus
// anciennes sont écrasées au-delà de LOG_BUFFER_MAX_LINES : diagnostic
// uniquement, rien de critique n'y transite (contrairement à la file de scans).
inline constexpr bool REMOTE_LOG_ENABLED = true;
inline constexpr char API_RELAY_LOGS_PATH[] = "/api/relay/logs";
// 10 s : chaque envoi ouvre puis ferme une connexion TLS (plus de keep-alive,
// pour rendre la mémoire aux scans) — inutile d'en ouvrir une toutes les 5 s.
inline constexpr uint32_t LOG_FLUSH_INTERVAL_MS = 10000;
inline constexpr uint32_t LOG_RETRY_INTERVAL_MS = 30000;
inline constexpr size_t LOG_BUFFER_MAX_LINES = 80;
inline constexpr size_t LOG_LINE_MAX_LEN = 240;
inline constexpr size_t LOG_BATCH_MAX_LINES = 30;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";

// ---- Mises à jour OTA (backoffice > Mises à jour firmware) ----
// Version de CE binaire, au format x.y.z. À incrémenter avant chaque build
// destiné à l'OTA et à saisir à l'identique dans le backoffice lors de
// l'upload : la borne flashe dès que la version active côté serveur diffère
// de celle-ci (activer une version plus ancienne fait donc un rollback).
inline constexpr char FIRMWARE_VERSION[] = "1.0.7";
inline constexpr char API_RELAY_FIRMWARE_MANIFEST_PATH[] = "/api/relay/firmware/manifest";
// Cadence de vérification du manifest (en plus d'une vérification dès la
// première connexion WiFi). Pas de canal push ici, contrairement au MQTT de
// campuspass : c'est le délai max entre l'activation et la mise à jour.
inline constexpr uint32_t OTA_CHECK_INTERVAL_MS = 10UL * 60UL * 1000UL;
inline constexpr uint32_t OTA_CONNECT_TIMEOUT_MS = 6000;
// Timeout d'INACTIVITÉ du téléchargement (le chrono repart à chaque paquet
// reçu), pas un timeout global : un lien lent mais vivant va au bout.
inline constexpr uint32_t OTA_STALL_TIMEOUT_MS = 15000;
// ===== Fin configuration =====

// Versions majeures attendues : les suivantes ont changé d'API (signatures des
// callbacks BLE, documents JSON) et ne compilent pas avec ce code.
#if ARDUINOJSON_VERSION_MAJOR != 6
#error "Installez ArduinoJson 6.21.x (Benoit Blanchon) — la version 7 n'est pas compatible."
#endif
#if !__has_include(<NimBLESecurity.h>)
#error "Installez NimBLE-Arduino 1.4.3 (h2zero) — la version 2.x n'est pas compatible."
#endif

// Types utilisés dans des signatures de fonctions, déclarés avant toute
// fonction : l'IDE Arduino (version fichier unique test.cpp) insère ses
// prototypes automatiques avant la première fonction du fichier.
/** Position d'une ligne (un paquet) dans le fichier de la file. */
struct QueueLine
{
    size_t start;  // offset du premier octet
    size_t length; // sans le '\n' final
};

struct LogLine
{
    uint32_t seq;
    uint32_t uptime_ms;
    String message;
};

struct OtaManifest
{
    String version;
    String url;
    String sha256; // hex, 64 caractères
    size_t sizeBytes;
};

enum class OtaResult
{
    SUCCESS,
    NETWORK_ERROR, // transitoire : nouvel essai au prochain cycle
    CORRUPT,       // SHA256 incorrect / image refusée : version mise de côté
};

// Équivalent Arduino IDE de -DCONFIG_ARDUINO_LOOP_STACK_SIZE=16384
// (platformio.ini) : pile de loop() assez grande pour TLS + buffers JSON.
// Placé après les types ci-dessus : la macro génère une fonction, avant
// laquelle l'IDE Arduino insère ses prototypes automatiques.
SET_LOOP_TASK_STACK_SIZE(16 * 1024);

static WebServer server(80);
static uint32_t g_local_id_counter = 0;
static bool g_time_ready = false;
static String g_ble_address;
// Un scan BLE complet attend d'être traité par loop() (voir ScanCharCallbacks).
static volatile bool g_pendingScan = false;

static void beepBuzzer()
{
    tone(BUZZER_GPIO, 2400, 100);
}

static void makeLocalId(char *out, size_t outLen)
{
    snprintf(out, outLen, "borne-%lu-%lu", (unsigned long)millis(), (unsigned long)(++g_local_id_counter));
}

// ---------------------------------------------------------------------------
// Persistance de la file (micro-SD si disponible, LittleFS sinon ; une ligne
// JSON par paquet en attente).
// ---------------------------------------------------------------------------

// La synchro tourne dans sa propre tâche FreeRTOS (voir setup()/syncTask) pour
// lui donner une pile dédiée assez grande pour la poignée de main TLS de
// mbedTLS — elle peut donc s'exécuter en parallèle de handleScan() (tâche
// loop()/webserver), qui touche le même fichier sur la flash.
static SemaphoreHandle_t g_fsMutex = nullptr;
static FS *g_queueFs = &LittleFS;
static bool g_sd_ready = false;

static size_t queueCapacity()
{
    return g_sd_ready ? MAX_QUEUE_SIZE_SD : MAX_QUEUE_SIZE_LITTLEFS;
}

struct MutexGuard
{
    explicit MutexGuard(SemaphoreHandle_t sem) : _sem(sem)
    {
        if (_sem)
            xSemaphoreTake(_sem, portMAX_DELAY);
    }
    ~MutexGuard()
    {
        if (_sem)
            xSemaphoreGive(_sem);
    }
    SemaphoreHandle_t _sem;
};

// ---------------------------------------------------------------------------
// Moniteur série à distance : tout ce qui passe par g_log est écrit sur le
// port série ET gardé ligne par ligne dans un tampon RAM borné, poussé vers
// l'API par flushRemoteLogs() (tâche de synchro) pour consultation dans le
// backoffice. Utiliser g_log à la place de Serial pour tout message utile au
// diagnostic ; Serial direct pour ce qui ne doit rester que local.
// ---------------------------------------------------------------------------

class RemoteLogger : public Print
{
public:
    void begin()
    {
        _mutex = xSemaphoreCreateMutex();
    }

    size_t write(uint8_t c) override
    {
        return write(&c, 1);
    }

    size_t write(const uint8_t *buf, size_t size) override
    {
        Serial.write(buf, size);
        if (!REMOTE_LOG_ENABLED)
            return size;

        MutexGuard guard(_mutex);
        for (size_t i = 0; i < size; i++)
        {
            const uint8_t c = buf[i];
            if (c == '\n')
            {
                commitLine();
            }
            else if (c != '\r' && acceptByte(c))
            {
                _current += static_cast<char>(c);
            }
        }
        return size;
    }

    /** Copie jusqu'à `max` lignes les plus anciennes, sans les retirer du tampon. */
    void snapshot(std::vector<LogLine> &out, size_t max)
    {
        MutexGuard guard(_mutex);
        for (size_t i = 0; i < _lines.size() && out.size() < max; i++)
            out.push_back(_lines[i]);
    }

    /** Retire les lignes confirmées par l'API (seq <= lastSeq). Les lignes
     * imprimées pendant l'envoi, ou celles déjà écrasées faute de place, ne
     * sont pas touchées. */
    void ack(uint32_t lastSeq)
    {
        MutexGuard guard(_mutex);
        while (!_lines.empty() && _lines.front().seq <= lastSeq)
            _lines.pop_front();
    }

private:
    /** Tronque à LOG_LINE_MAX_LEN sans jamais couper un caractère UTF-8 en
     * deux (accents des messages) : une séquence invalide ferait rejeter tout
     * le lot par le décodeur JSON de l'API. */
    bool acceptByte(uint8_t c)
    {
        if (_overflow)
            return false;
        const bool continuation = (c & 0xC0) == 0x80;
        if (_current.length() < LOG_LINE_MAX_LEN - 4 || (continuation && _current.length() < LOG_LINE_MAX_LEN))
            return true;
        _overflow = true;
        return false;
    }

    void commitLine()
    {
        if (_current.length() > 0)
        {
            if (_lines.size() >= LOG_BUFFER_MAX_LINES)
                _lines.pop_front();
            if (_overflow)
                _current += "…";
            _lines.push_back({++_seq, (uint32_t)millis(), _current});
        }
        _current = "";
        _overflow = false;
    }

    SemaphoreHandle_t _mutex = nullptr;
    std::deque<LogLine> _lines;
    String _current;
    bool _overflow = false;
    uint32_t _seq = 0;
};

static RemoteLogger g_log;

static size_t queueLength()
{
    MutexGuard guard(g_fsMutex);
    if (!g_queueFs->exists(QUEUE_FILE))
        return 0;
    File f = g_queueFs->open(QUEUE_FILE, "r");
    if (!f)
        return 0;
    // Comptage par blocs, sans construire une String par ligne : une ligne
    // avec selfie pèse ~10 Ko, autant d'allocations qui morcellent le tas.
    size_t n = 0;
    bool lineHasContent = false;
    uint8_t buf[256];
    int read;
    while ((read = f.read(buf, sizeof(buf))) > 0)
    {
        for (int i = 0; i < read; i++)
        {
            if (buf[i] == '\n')
            {
                if (lineHasContent)
                    n++;
                lineHasContent = false;
            }
            else if (buf[i] != '\r' && buf[i] != ' ')
            {
                lineHasContent = true;
            }
        }
    }
    if (lineHasContent)
        n++;
    f.close();
    return n;
}

// Marqueur posé à la place du selfie dans le JSON du paquet (voir
// processScan) : appendToQueue() le remplace par le base64 de la photo,
// encodé par blocs directement vers le fichier.
static const char PHOTO_PLACEHOLDER[] = "@@PHOTO@@";

/**
 * Ajoute un paquet à la file. Si `photo` n'est pas vide, son base64 est écrit
 * à l'emplacement de PHOTO_PLACEHOLDER, par blocs de 576 octets (multiple de
 * 3 : pas de remplissage "=" au milieu) — jamais de copie base64 complète en
 * RAM, contrairement à l'ancienne version (document JSON de 20 Ko + String
 * base64 + String sérialisée).
 *
 * false si la ligne n'a pas été entièrement écrite (flash/SD pleine ou
 * absente) : l'appelant ne doit alors PAS confirmer le scan au téléphone.
 */
static bool appendToQueue(const String &json, const std::vector<uint8_t> &photo)
{
    MutexGuard guard(g_fsMutex);
    File f = g_queueFs->open(QUEUE_FILE, "a", true);
    if (!f)
    {
        g_log.println("[queue] échec ouverture flash en écriture");
        return false;
    }

    size_t expected = 0;
    size_t written = 0;
    const int at = photo.empty() ? -1 : json.indexOf(PHOTO_PLACEHOLDER);
    if (at < 0)
    {
        expected += json.length();
        written += f.print(json);
    }
    else
    {
        expected += at;
        written += f.write(reinterpret_cast<const uint8_t *>(json.c_str()), at);

        unsigned char encoded[769]; // 576 octets -> 768 caractères + '\0'
        for (size_t offset = 0; offset < photo.size(); offset += 576)
        {
            const size_t n = std::min<size_t>(576, photo.size() - offset);
            size_t encodedLen = 0;
            mbedtls_base64_encode(encoded, sizeof(encoded), &encodedLen, photo.data() + offset, n);
            expected += encodedLen;
            written += f.write(encoded, encodedLen);
        }

        const char *tail = json.c_str() + at + strlen(PHOTO_PLACEHOLDER);
        expected += strlen(tail);
        written += f.print(tail);
    }
    expected += 1;
    written += f.print('\n');
    f.close();

    if (written != expected)
    {
        // La ligne partielle éventuelle sera purgée comme invalide (voir removeFromQueue).
        g_log.printf("[queue] écriture incomplète (%u/%u octets), stockage plein ?\n", (unsigned)written, (unsigned)expected);
        return false;
    }
    return true;
}

/** Un paquet exploitable : objet JSON avec un local_id non vide. Une ligne
 * `null`, un scalaire ou un objet sans local_id (écrit par une version
 * antérieure à cause d'un DynamicJsonDocument non alloué, voir processScan)
 * ne passera jamais la validation de l'API et ne peut pas être purgé par son
 * local_id : il bloquerait la file indéfiniment (422 en boucle). */
static bool isValidPacket(const JsonDocument &doc)
{
    const char *localId = doc["local_id"] | "";
    return doc.is<JsonObjectConst>() && localId[0] != '\0';
}

/** Lecteur ArduinoJson limité à une ligne de la file. Sans cette borne, une
 * ligne tronquée (coupure pendant l'écriture) ferait déborder l'analyse sur
 * la ligne suivante. */
struct BoundedFileReader
{
    File &file;
    size_t remaining;

    int read()
    {
        if (remaining == 0)
            return -1;
        remaining--;
        return file.read();
    }

    size_t readBytes(char *buffer, size_t length)
    {
        const size_t n = file.readBytes(buffer, std::min(length, remaining));
        remaining -= n;
        return n;
    }
};

/** Place le curseur juste après la ligne (borné à la taille du fichier : un
 * seek au-delà de la fin échoue sur LittleFS et laisserait le curseur en place). */
static void skipQueueLine(File &f, const QueueLine &line)
{
    f.seek(std::min<size_t>(line.start + line.length + 1, f.size()));
}

/**
 * Place le curseur au début de la prochaine ligne non vide et en donne la
 * position/longueur ; false en fin de fichier. La file est parcourue par
 * blocs, directement dans le fichier : plus aucune ligne n'est chargée dans
 * une String pour être simplement inspectée ou recopiée. Une ligne avec
 * selfie pèse ~10 Ko et String grossit par pas de 16 octets : des centaines
 * de réallocations, à chaque cycle de synchro, qui morcelaient le tas
 * jusqu'à priver mbedTLS de ses blocs de 17 Ko ("SSL - Memory allocation
 * failed").
 */
static bool nextQueueLine(File &f, QueueLine &line)
{
    uint8_t buf[128];
    for (;;)
    {
        line.start = f.position();
        line.length = 0;
        bool ended = false;
        bool hasContent = false;
        int read;
        while (!ended && (read = f.read(buf, sizeof(buf))) > 0)
        {
            for (int i = 0; i < read; i++)
            {
                if (buf[i] == '\n')
                {
                    ended = true;
                    break;
                }
                if (buf[i] != '\r' && buf[i] != ' ')
                    hasContent = true;
                line.length++;
            }
        }
        if (hasContent)
        {
            f.seek(line.start);
            return true;
        }
        if (!ended)
            return false; // fin de fichier
        skipQueueLine(f, line);
    }
}

/**
 * Analyse une ligne de la file, lue directement dans le fichier, en n'en
 * retenant que `local_id` (filtre ArduinoJson) : un petit document sur la
 * pile suffit, quel que soit le poids du selfie.
 */
static DeserializationError parseLocalId(File &f, const QueueLine &line, JsonDocument &doc)
{
    StaticJsonDocument<32> filter;
    filter["local_id"] = true;
    f.seek(line.start);
    BoundedFileReader reader{f, line.length};
    return deserializeJson(doc, reader, DeserializationOption::Filter(filter));
}

/**
 * Charge le premier paquet exploitable de la file dans `body`, déjà enveloppé
 * dans le corps attendu par l'API ({"packets":[...]}) : une seule copie du
 * paquet en RAM, allouée d'un coup à sa taille exacte (l'ancienne version en
 * tenait jusqu'à quatre au moment d'ouvrir la connexion TLS). Un paquet par
 * requête : la négociation TLS consomme déjà l'essentiel de la RAM libre.
 * true si un paquet a été chargé.
 */
static bool readQueueHead(String &body, String &localId)
{
    static const char PREFIX[] = "{\"packets\":[";

    MutexGuard guard(g_fsMutex);
    if (!g_queueFs->exists(QUEUE_FILE))
        return false;

    File f = g_queueFs->open(QUEUE_FILE, "r");
    if (!f)
        return false;

    StaticJsonDocument<192> doc;
    QueueLine line;
    unsigned skipped = 0;
    bool loaded = false;
    while (nextQueueLine(f, line))
    {
        if (parseLocalId(f, line, doc) != DeserializationError::Ok || !isValidPacket(doc))
        {
            skipped++;
            skipQueueLine(f, line);
            continue;
        }

        localId = doc["local_id"].as<const char *>();
        if (!body.reserve(sizeof(PREFIX) + line.length + 2))
        {
            g_log.printf("[queue] mémoire insuffisante pour charger un paquet de %u o, nouvel essai plus tard\n", (unsigned)line.length);
            break;
        }
        body = PREFIX;
        f.seek(line.start);
        char buf[256];
        size_t remaining = line.length;
        while (remaining > 0)
        {
            const size_t n = f.readBytes(buf, std::min(remaining, sizeof(buf)));
            if (n == 0)
                break;
            body.concat(buf, n);
            remaining -= n;
        }
        body += "]}";
        loaded = remaining == 0;
        break;
    }
    f.close();
    if (skipped > 0)
        g_log.printf("[queue] %u ligne(s) invalide(s) ignorée(s), purgées à la prochaine confirmation\n", skipped);
    return loaded;
}

// Fichier de travail de removeFromQueue() (voir aussi recoverQueueRewrite()).
static const char QUEUE_TMP_FILE[] = "/queue.tmp";

/**
 * Réécrit la file sans les local_id passés en paramètre (terminaux : ok ou
 * rejected), recopiée par blocs vers un fichier temporaire qui remplace
 * ensuite l'original — aucune ligne n'est chargée en RAM.
 */
static void removeFromQueue(const std::vector<String> &idsToRemove)
{
    if (idsToRemove.empty())
        return;
    MutexGuard guard(g_fsMutex);
    if (!g_queueFs->exists(QUEUE_FILE))
        return;

    File in = g_queueFs->open(QUEUE_FILE, "r");
    if (!in)
        return;
    File out = g_queueFs->open(QUEUE_TMP_FILE, "w", true);
    if (!out)
    {
        in.close();
        return;
    }

    StaticJsonDocument<192> doc;
    QueueLine line;
    uint8_t buf[256];
    unsigned purged = 0;
    bool writeOk = true;
    while (writeOk && nextQueueLine(in, line))
    {
        bool keep = true;
        const DeserializationError err = parseLocalId(in, line, doc);
        if (err == DeserializationError::NoMemory)
        {
            // local_id anormalement long pour le petit document : pas
            // forcément invalide, on garde la ligne telle quelle.
        }
        else if (err || !isValidPacket(doc))
        {
            // Ligne tronquée (coupure pendant l'écriture) ou paquet vide
            // (voir isValidPacket) : jamais exploitable, on la purge.
            purged++;
            keep = false;
        }
        else
        {
            const char *id = doc["local_id"] | "";
            for (const auto &rid : idsToRemove)
            {
                if (rid == id)
                {
                    keep = false;
                    break;
                }
            }
        }

        if (keep)
        {
            in.seek(line.start);
            size_t remaining = line.length;
            while (remaining > 0 && writeOk)
            {
                const int n = in.read(buf, std::min(remaining, sizeof(buf)));
                if (n <= 0)
                    break;
                writeOk = out.write(buf, n) == static_cast<size_t>(n);
                remaining -= n;
            }
            writeOk = writeOk && remaining == 0 && out.write('\n') == 1;
        }
        skipQueueLine(in, line);
    }
    in.close();
    out.close();

    if (!writeOk)
    {
        // Stockage plein : on garde la file d'origine intacte (les paquets
        // confirmés seront renvoyés puis rejetés/dédoublonnés par l'API).
        g_queueFs->remove(QUEUE_TMP_FILE);
        g_log.println("[queue] réécriture de la file impossible (stockage plein ?), file conservée");
        return;
    }

    g_queueFs->remove(QUEUE_FILE);
    g_queueFs->rename(QUEUE_TMP_FILE, QUEUE_FILE);
    if (purged > 0)
        g_log.printf("[queue] %u ligne(s) invalide(s) purgée(s) de la file\n", purged);
}

/**
 * Au démarrage : répare une réécriture de file interrompue par une coupure
 * de courant. Fichier temporaire seul (coupure entre la suppression de
 * l'original et le renommage) : c'est la file, on le renomme. Les deux
 * présents (coupure pendant l'écriture) : le temporaire est incomplet, on le
 * jette et on garde l'original.
 */
static void recoverQueueRewrite()
{
    MutexGuard guard(g_fsMutex);
    if (!g_queueFs->exists(QUEUE_TMP_FILE))
        return;
    if (g_queueFs->exists(QUEUE_FILE))
    {
        g_queueFs->remove(QUEUE_TMP_FILE);
    }
    else
    {
        g_queueFs->rename(QUEUE_TMP_FILE, QUEUE_FILE);
        g_log.println("[queue] file restaurée après une réécriture interrompue");
    }
}

static void setupStorage()
{
    if (!LittleFS.begin(true))
        g_log.println("[fs] échec montage LittleFS");

    g_queueFs = &LittleFS;
    pinMode(SD_CS_GPIO, OUTPUT);
    digitalWrite(SD_CS_GPIO, HIGH);
    SPI.begin(SD_SCK_GPIO, SD_MISO_GPIO, SD_MOSI_GPIO, SD_CS_GPIO);
    if (!SD.begin(SD_CS_GPIO, SPI, SD_SPI_FREQUENCY_HZ, "/sdcard", 5, false))
    {
        g_log.printf("[sd] échec initialisation SPI (CS=%u, SCK=%u, MISO=%u, MOSI=%u) ; vérifiez câblage, 3.3 V et FAT32\n",
                      SD_CS_GPIO, SD_SCK_GPIO, SD_MISO_GPIO, SD_MOSI_GPIO);
        g_log.println("[sd] file LittleFS active");
        return;
    }

    // Une ancienne file LittleFS peut contenir des scans créés avant l'ajout
    // du lecteur. On la transfère seulement si la carte est encore vierge.
    if (!SD.exists(QUEUE_FILE) && LittleFS.exists(QUEUE_FILE))
    {
        File source = LittleFS.open(QUEUE_FILE, "r");
        File destination = SD.open(QUEUE_FILE, "w");
        if (source && destination)
        {
            while (source.available())
                destination.write(source.read());
            destination.close();
            source.close();
            LittleFS.remove(QUEUE_FILE);
            g_log.println("[sd] ancienne file LittleFS transférée");
        }
        else
        {
            if (source)
                source.close();
            if (destination)
                destination.close();
            g_log.println("[sd] échec transfert de la file LittleFS");
        }
    }

    g_queueFs = &SD;
    g_sd_ready = true;
    g_log.printf("[sd] carte détectée, file SD active (%llu Mo libres)\n",
                  (unsigned long long)((SD.totalBytes() - SD.usedBytes()) / (1024 * 1024)));
}

// ---------------------------------------------------------------------------
// Moteur de pull périodique vers l'API
// ---------------------------------------------------------------------------

/**
 * Client TLS statique et réutilisé d'un cycle à l'autre (évite l'overhead de
 * reconstruire l'objet WiFiClientSecure à chaque appel ; les buffers mbedTLS
 * eux-mêmes sont (dé)alloués par connect()/stop(), pas par le cycle de vie de
 * cet objet — setBufferSizes() n'existe pas sur cette version du core
 * arduino-esp32 (basée esp-idf 5, buffers fixes). Partagé par syncWithApi()
 * et flushRemoteLogs(), appelées l'une après l'autre depuis syncTask : jamais
 * deux connexions TLS simultanées sur cet ESP32 sans PSRAM.
 */
static WiFiClientSecure &apiClient()
{
    static WiFiClientSecure secureClient;
    static bool secureClientReady = false;
    if (!secureClientReady)
    {
        secureClient.setInsecure(); // pas de CA pinnée, cf. comportement HTTPClient par défaut jusqu'ici
        // Par défaut, une poignée de main TLS qui n'aboutit pas (liaison
        // radio médiocre, serveur qui ne répond plus en cours de route)
        // bloque la tâche de synchro 120 s avant d'échouer en "-1".
        secureClient.setHandshakeTimeout(HTTPS_HANDSHAKE_TIMEOUT_S);
        secureClientReady = true;
    }
    return secureClient;
}

// ---------------------------------------------------------------------------
// Réserve mémoire TLS. mbedTLS (tel que compilé dans le core Arduino : pas de
// tampons dynamiques) alloue à chaque connexion deux blocs de ~16,7 Ko d'un
// seul tenant, plus quelques Ko pour la poignée de main. Sur ce module sans
// PSRAM, BLE + WiFi (jusqu'à 32 tampons RX dynamiques de 1,6 Ko quand la
// liaison est mauvaise) morcellent le tas : passé quelques minutes, ces blocs
// n'étaient plus allouables ("SSL - Memory allocation failed" en boucle).
// On les réserve donc au démarrage, tas encore propre, et on ne les prête
// qu'au TLS, le temps d'une requête (TlsSessionScope).
// ---------------------------------------------------------------------------

static void *g_tlsReserve[sizeof(TLS_RESERVE_BLOCK_SIZES) / sizeof(TLS_RESERVE_BLOCK_SIZES[0])] = {};
static uint8_t g_tlsMemoryFailures = 0;

static void logMemory(const char *context)
{
    g_log.printf("[mem] %s : libre=%u o, plus grand bloc=%u o, minimum atteint=%u o\n",
                 context, (unsigned)ESP.getFreeHeap(), (unsigned)ESP.getMaxAllocHeap(), (unsigned)ESP.getMinFreeHeap());
}

static void reserveTlsMemory()
{
    for (size_t i = 0; i < sizeof(g_tlsReserve) / sizeof(g_tlsReserve[0]); i++)
    {
        if (!g_tlsReserve[i])
            g_tlsReserve[i] = malloc(TLS_RESERVE_BLOCK_SIZES[i]);
    }
}

static void releaseTlsMemory()
{
    for (auto &block : g_tlsReserve)
    {
        free(block);
        block = nullptr;
    }
}

// Remèdes activés par diagnoseConnectivity() quand la poignée de main TLS
// n'aboutit pas alors que le réseau fonctionne (voir cette fonction).
static bool g_pauseBleDuringTls = false;
static bool otaSafeToRun();

/**
 * Encadre une connexion TLS. À déclarer AVANT le HTTPClient de la fonction :
 * détruit après lui, il ne reprend la réserve mémoire qu'une fois la
 * connexion fermée et ses tampons rendus.
 *  - prête la réserve mémoire au TLS ;
 *  - si g_pauseBleDuringTls : suspend la publicité BLE le temps de la
 *    connexion (WiFi et BLE partagent la même radio), sauf si un téléphone
 *    est en train de pointer.
 */
struct TlsSessionScope
{
    bool advertisingPaused = false;

    TlsSessionScope()
    {
        releaseTlsMemory();
        if (g_pauseBleDuringTls && otaSafeToRun())
        {
            NimBLEDevice::getAdvertising()->stop();
            advertisingPaused = true;
        }
    }

    ~TlsSessionScope()
    {
        if (advertisingPaused)
            NimBLEDevice::getAdvertising()->start();
        reserveTlsMemory();
    }
};

/**
 * À appeler après chaque requête HTTPS. En cas d'échec de connexion, imprime
 * de quoi distinguer les causes : durée (≈ HTTPS_CONNECT_TIMEOUT_MS : serveur
 * injoignable ; ≈ HTTPS_HANDSHAKE_TIMEOUT_S : poignée de main TLS qui
 * n'aboutit pas ; quasi immédiat : DNS), force du signal WiFi et code
 * d'erreur mbedTLS. Compte aussi les échecs dus à la mémoire (voir le
 * redémarrage de secours dans syncTask).
 * `out` : g_log, ou Serial pour l'envoi des journaux lui-même (sinon chaque
 * échec d'envoi ajouterait une ligne à envoyer).
 */
static void noteTlsResult(int httpStatus, uint32_t startedAtMs, Print &out)
{
    if (httpStatus > 0)
    {
        g_tlsMemoryFailures = 0;
        return;
    }
    char unused[8];
    const int tlsError = apiClient().lastError(unused, sizeof(unused));
    out.printf("[net] connexion à l'API impossible après %u ms (WiFi %d dBm, code TLS %d)\n",
               (unsigned)(millis() - startedAtMs), (int)WiFi.RSSI(), tlsError);
    if (tlsError == MBEDTLS_ERR_SSL_ALLOC_FAILED)
    {
        if (g_tlsMemoryFailures < 255)
            g_tlsMemoryFailures++;
        logMemory("connexion TLS impossible faute de mémoire");
    }
}

/** true : rien à envoyer, ou paquet confirmé/rejeté par l'API ; false : échec
 * (réseau, API, mémoire) — l'appelant espace alors la tentative suivante. */
static bool syncWithApi()
{
    if (WiFi.status() != WL_CONNECTED)
        return true;

    String body;
    String localId;
    if (!readQueueHead(body, localId))
        return true;

    int status;
    String respBody;
    const uint32_t startedAt = millis();
    {
        TlsSessionScope tlsSession;
        HTTPClient http;
        http.begin(apiClient(), String(API_BASE_URL) + API_RELAY_SYNC_PATH);
        http.setConnectTimeout(HTTPS_CONNECT_TIMEOUT_MS);
        // Connexion fermée après la requête : gardée ouverte (keep-alive), la
        // session TLS immobilisait en permanence ses tampons mbedTLS.
        http.setReuse(false);
        http.addHeader("Content-Type", "application/json");
        http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
        http.setTimeout(20000);
        // Pointeur + taille : POST(String) prend son argument par valeur et
        // dupliquerait le paquet (10 Ko avec selfie) pendant la connexion.
        status = http.POST(reinterpret_cast<uint8_t *>(const_cast<char *>(body.c_str())), body.length());
        respBody = http.getString();
        http.end();
    }
    noteTlsResult(status, startedAt, g_log);
    body = String(); // paquet libéré avant d'analyser la réponse

    if (status != 200)
    {
        // Rien n'est retiré de la file : nouvelle tentative plus tard.
        g_log.printf("[sync] échec HTTP %d: %s, nouvel essai dans %u s\n", status, respBody.c_str(), (unsigned)(SYNC_RETRY_INTERVAL_MS / 1000));
        return false;
    }

    StaticJsonDocument<1024> resp;
    if (deserializeJson(resp, respBody) != DeserializationError::Ok)
    {
        g_log.println("[sync] réponse API illisible, on retentera plus tard");
        return false;
    }

    std::vector<String> toRemove;
    for (JsonObject result : resp["results"].as<JsonArray>())
    {
        const char *status_ = result["status"] | "";
        const char *resultId = result["local_id"] | "";
        // "ok" (accepté) et "rejected" (invalide, inutile de réessayer) sont
        // terminaux : on purge. "retry" reste en file pour le prochain cycle.
        if (strcmp(status_, "ok") == 0 || strcmp(status_, "rejected") == 0)
        {
            toRemove.push_back(String(resultId));
        }
    }

    removeFromQueue(toRemove);
    g_log.printf("[sync] 1 paquet(s) envoyés, %u confirmé(s)/rejeté(s)\n", (unsigned)toRemove.size());
    return !toRemove.empty();
}

/**
 * Pousse vers l'API les lignes du moniteur série en attente (voir
 * RemoteLogger). Les échecs ne sont imprimés que sur Serial : les passer par
 * g_log alimenterait le tampon avec ses propres erreurs d'envoi.
 * false en cas d'échec réseau : l'appelant espace la tentative suivante.
 */
static bool flushRemoteLogs()
{
    if (!REMOTE_LOG_ENABLED || WiFi.status() != WL_CONNECTED)
        return true;

    std::vector<LogLine> batch;
    g_log.snapshot(batch, LOG_BATCH_MAX_LINES);
    if (batch.empty())
        return true;

    // Capacité calculée sur le contenu réel plutôt que sur le pire cas
    // (LOG_BATCH_MAX_LINES x LOG_LINE_MAX_LEN) : le tas est déjà sollicité
    // par la poignée de main TLS qui suit.
    size_t capacity = JSON_OBJECT_SIZE(2) + JSON_ARRAY_SIZE(batch.size()) + batch.size() * JSON_OBJECT_SIZE(2) + 64;
    for (const auto &line : batch)
        capacity += line.message.length() + 1;

    DynamicJsonDocument doc(capacity);
    doc["uptime_ms"] = (uint32_t)millis();
    JsonArray lines = doc.createNestedArray("lines");
    for (const auto &line : batch)
    {
        JsonObject o = lines.createNestedObject();
        o["uptime_ms"] = line.uptime_ms;
        o["message"] = line.message;
    }
    const uint32_t lastSeq = batch.back().seq;
    batch.clear();

    String body;
    serializeJson(doc, body);
    doc.clear();

    int status;
    const uint32_t startedAt = millis();
    {
        TlsSessionScope tlsSession;
        HTTPClient http;
        http.begin(apiClient(), String(API_BASE_URL) + API_RELAY_LOGS_PATH);
        http.setConnectTimeout(HTTPS_CONNECT_TIMEOUT_MS);
        http.setReuse(false); // voir syncWithApi() : libère les tampons TLS entre deux requêtes
        http.addHeader("Content-Type", "application/json");
        http.addHeader("Accept", "application/json");
        http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
        http.setTimeout(10000);
        status = http.POST(reinterpret_cast<uint8_t *>(const_cast<char *>(body.c_str())), body.length());
        http.end();
    }
    noteTlsResult(status, startedAt, Serial);

    if (status >= 200 && status < 300)
    {
        g_log.ack(lastSeq);
        return true;
    }
    if (status >= 400 && status < 500 && status != 429)
    {
        // Lot refusé tel quel (validation, token révoqué...) : il ne passera
        // jamais, on le jette plutôt que de bloquer les lignes suivantes.
        Serial.printf("[log] lot refusé par l'API (HTTP %d), ignoré\n", status);
        g_log.ack(lastSeq);
        return true;
    }
    Serial.printf("[log] échec envoi (HTTP %d), nouvelle tentative plus tard\n", status);
    return false;
}

// ---------------------------------------------------------------------------
// Mises à jour OTA — même principe que campuspass_hardware (OtaManager) :
// manifest -> téléchargement en flux -> SHA256 vérifié avant d'activer le
// nouveau slot -> redémarrage. Différences : manifest et binaire servis par
// l'API authentifiée (le binaire contient RELAY_API_TOKEN, il ne peut pas
// être public), et vérification périodique faute de canal push (MQTT).
// ---------------------------------------------------------------------------

static bool g_otaAppValidated = false;

/** Valide le slot courant (annule le rollback automatique du bootloader,
 * s'il est activé) dès que ce firmware a prouvé qu'il joint l'API : un
 * firmware OTA incapable de se connecter ne serait jamais validé. */
static void markOtaAppValidOnce()
{
    if (g_otaAppValidated)
        return;
    g_otaAppValidated = true;
    if (esp_ota_mark_app_valid_cancel_rollback() == ESP_OK)
        g_log.println("[ota] slot courant validé");
}

static bool sha256Matches(const uint8_t digest[32], const String &expectedHex)
{
    if (expectedHex.length() != 64)
        return false;
    char computed[65];
    for (int i = 0; i < 32; i++)
        sprintf(computed + i * 2, "%02x", digest[i]);
    computed[64] = '\0';
    return expectedHex.equalsIgnoreCase(String(computed));
}

/** true + manifest rempli si une version active existe ; false sinon (204,
 * erreur réseau, réponse invalide). */
static bool fetchOtaManifest(OtaManifest &out)
{
    const uint32_t startedAt = millis();
    TlsSessionScope tlsSession;
    HTTPClient http;
    http.setConnectTimeout(OTA_CONNECT_TIMEOUT_MS);
    http.setTimeout(10000);
    http.begin(apiClient(), String(API_BASE_URL) + API_RELAY_FIRMWARE_MANIFEST_PATH);
    http.setReuse(false); // voir syncWithApi() : libère les tampons TLS entre deux requêtes
    http.addHeader("Accept", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.addHeader("X-Firmware-Version", FIRMWARE_VERSION);

    const int code = http.GET();
    noteTlsResult(code, startedAt, g_log);
    if (code == 200 || code == 204)
        markOtaAppValidOnce();
    if (code != 200)
    {
        if (code != 204)
            g_log.printf("[ota] manifest HTTP %d\n", code);
        http.end();
        return false;
    }

    StaticJsonDocument<768> doc;
    const DeserializationError err = deserializeJson(doc, http.getString());
    http.end();
    if (err)
    {
        g_log.println("[ota] manifest JSON invalide");
        return false;
    }

    out.version = doc["version"].as<String>();
    out.url = doc["url"].as<String>();
    out.sha256 = doc["sha256"].as<String>();
    out.sizeBytes = doc["size_bytes"] | (size_t)0;
    if (out.version.isEmpty() || out.url.isEmpty() || out.sha256.length() != 64 || out.sizeBytes == 0)
    {
        g_log.println("[ota] manifest incomplet");
        return false;
    }
    return true;
}

static OtaResult downloadAndFlash(const OtaManifest &m)
{
    TlsSessionScope tlsSession;
    HTTPClient http;
    http.setConnectTimeout(OTA_CONNECT_TIMEOUT_MS);
    http.setTimeout(OTA_STALL_TIMEOUT_MS);
    http.begin(apiClient(), m.url);
    http.setReuse(false); // voir syncWithApi() : libère les tampons TLS entre deux requêtes
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);

    const int code = http.GET();
    if (code != 200)
    {
        g_log.printf("[ota] téléchargement HTTP %d\n", code);
        http.end();
        return OtaResult::NETWORK_ERROR;
    }

    if (!Update.begin(m.sizeBytes, U_FLASH))
    {
        g_log.printf("[ota] Update.begin() refusé : %s\n", Update.errorString());
        http.end();
        // Image trop grande pour le slot : la retenter ne changera rien.
        return OtaResult::CORRUPT;
    }

    mbedtls_md_context_t ctx;
    mbedtls_md_init(&ctx);
    mbedtls_md_setup(&ctx, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256), 0);
    mbedtls_md_starts(&ctx);

    WiFiClient *stream = http.getStreamPtr();
    static uint8_t buf[2048]; // hors pile : syncTask est déjà chargée par TLS
    size_t written = 0;
    size_t nextLogAt = m.sizeBytes / 10;
    bool failed = false;
    stream->setTimeout(1000);
    uint32_t lastDataMs = millis();

    while (written < m.sizeBytes)
    {
        const size_t n = stream->readBytes(buf, std::min(m.sizeBytes - written, sizeof(buf)));
        if (n == 0)
        {
            if (!http.connected() || millis() - lastDataMs > OTA_STALL_TIMEOUT_MS)
            {
                g_log.printf("[ota] flux interrompu (%u/%u octets)\n", (unsigned)written, (unsigned)m.sizeBytes);
                failed = true;
                break;
            }
            continue;
        }
        lastDataMs = millis();

        if (Update.write(buf, n) != n)
        {
            g_log.printf("[ota] Update.write() : %s\n", Update.errorString());
            failed = true;
            break;
        }
        mbedtls_md_update(&ctx, buf, n);
        written += n;

        if (written >= nextLogAt)
        {
            g_log.printf("[ota] %u%%\n", (unsigned)(100ULL * written / m.sizeBytes));
            nextLogAt += m.sizeBytes / 10;
        }
    }
    http.end();

    uint8_t digest[32];
    mbedtls_md_finish(&ctx, digest);
    mbedtls_md_free(&ctx);

    if (failed)
    {
        Update.abort();
        return OtaResult::NETWORK_ERROR;
    }
    if (!sha256Matches(digest, m.sha256))
    {
        g_log.println("[ota] SHA256 incorrect, mise à jour annulée");
        Update.abort();
        return OtaResult::CORRUPT;
    }
    if (!Update.end(true))
    {
        g_log.printf("[ota] Update.end() : %s\n", Update.errorString());
        return OtaResult::CORRUPT;
    }
    return OtaResult::SUCCESS;
}

/** Pas de mise à jour pendant qu'un téléphone est connecté ou qu'un scan
 * attend d'être traité : le redémarrage couperait le pointage en cours. */
static bool otaSafeToRun()
{
    NimBLEServer *bleServer = NimBLEDevice::getServer();
    return !g_pendingScan && (!bleServer || bleServer->getConnectedCount() == 0);
}

/** Vérifie le manifest et applique la mise à jour si besoin. Ne retourne pas
 * en cas de succès (redémarrage). Appelée depuis syncTask (pile 16 Ko). */
static void checkForUpdate()
{
    OtaManifest manifest;
    if (!fetchOtaManifest(manifest) || manifest.version == FIRMWARE_VERSION)
        return;

    Preferences prefs;
    prefs.begin("ota", false);
    const String badVersion = prefs.getString("bad_version", "");
    prefs.end();
    if (manifest.version == badVersion)
        return; // déjà en échec (fichier corrompu) : on attend une autre version

    g_log.printf("[ota] mise à jour %s -> %s (%u octets)\n", FIRMWARE_VERSION, manifest.version.c_str(), (unsigned)manifest.sizeBytes);

    // Borne invisible pendant le téléchargement : un téléphone qui s'y
    // connecterait verrait son pointage coupé par le redémarrage final.
    NimBLEDevice::getAdvertising()->stop();
    const OtaResult result = downloadAndFlash(manifest);

    if (result == OtaResult::SUCCESS)
    {
        prefs.begin("ota", false);
        prefs.remove("bad_version");
        prefs.end();
        g_log.println("[ota] flash OK, redémarrage");
        flushRemoteLogs(); // dernières lignes visibles au backoffice avant le reboot
        delay(500);
        ESP.restart();
    }

    if (result == OtaResult::CORRUPT)
    {
        prefs.begin("ota", false);
        prefs.putString("bad_version", manifest.version);
        prefs.end();
        g_log.printf("[ota] version %s mise de côté\n", manifest.version.c_str());
    }
    else
    {
        g_log.println("[ota] erreur réseau, nouvel essai au prochain cycle");
    }
    NimBLEDevice::getAdvertising()->start();
}

/** GET HTTPS vers le site de référence, dans les mêmes conditions que les
 * appels à l'API. Renvoie le code HTTP (> 0) ou l'erreur HTTPClient (< 0). */
static int httpsProbe(uint32_t &elapsedMs)
{
    const uint32_t startedAt = millis();
    int code;
    {
        TlsSessionScope tlsSession;
        HTTPClient http;
        http.begin(apiClient(), NET_DIAG_HTTPS_URL);
        http.setReuse(false);
        http.setConnectTimeout(HTTPS_CONNECT_TIMEOUT_MS);
        http.setTimeout(8000);
        code = http.GET();
        http.end();
    }
    elapsedMs = millis() - startedAt;
    return code;
}

/**
 * Auto-diagnostic lancé quand l'API ne répond pas, pour savoir OÙ ça bloque
 * sans matériel de mesure, et y remédier seul quand c'est possible :
 *  1. HTTP sans TLS vers l'hôte de l'API : le serveur est-il joignable ?
 *  2. Gros téléchargement HTTP sans TLS : la borne reçoit-elle des trames
 *     pleines (une poignée de main TLS en exige plusieurs Ko d'affilée, une
 *     redirection HTTP tient dans un seul petit paquet) ?
 *  3. HTTPS vers un site de référence : le TLS marche-t-il avec un autre ?
 *  4. Si non, même test avec la publicité BLE suspendue (WiFi et BLE se
 *     partagent la radio) — s'il passe, le remède est conservé.
 *  5. Si non, même test avec une puissance d'émission WiFi réduite (pics de
 *     courant plus faibles : alimentation USB limite) — idem.
 */
static void diagnoseConnectivity()
{
    {
        String url = API_BASE_URL;
        url.replace("https://", "http://");
        WiFiClient plainClient;
        HTTPClient http;
        const uint32_t startedAt = millis();
        http.begin(plainClient, url);
        http.setConnectTimeout(HTTPS_CONNECT_TIMEOUT_MS);
        http.setTimeout(8000);
        const int code = http.GET();
        http.end();
        g_log.printf("[net] test 1/5, HTTP sans TLS vers l'API : %d en %u ms (code > 0 = serveur joignable)\n",
                     code, (unsigned)(millis() - startedAt));
    }
    {
        WiFiClient plainClient;
        HTTPClient http;
        const uint32_t startedAt = millis();
        http.begin(plainClient, NET_DIAG_HTTP_LARGE_URL);
        http.setConnectTimeout(HTTPS_CONNECT_TIMEOUT_MS);
        http.setTimeout(8000);
        const int code = http.GET();
        size_t received = 0;
        if (code > 0)
        {
            WiFiClient *stream = http.getStreamPtr();
            uint8_t buf[512];
            uint32_t lastData = millis();
            while (received < NET_DIAG_LARGE_BYTES && millis() - lastData < 5000)
            {
                const int n = stream->available() ? stream->read(buf, sizeof(buf)) : 0;
                if (n > 0)
                {
                    received += n;
                    lastData = millis();
                }
                else if (!stream->connected())
                {
                    break;
                }
                else
                {
                    delay(10);
                }
            }
        }
        http.end();
        g_log.printf("[net] test 2/5, gros téléchargement HTTP sans TLS : code %d, %u o reçus sur %u en %u ms\n",
                     code, (unsigned)received, (unsigned)NET_DIAG_LARGE_BYTES, (unsigned)(millis() - startedAt));
    }

    uint32_t elapsed = 0;
    int code = httpsProbe(elapsed);
    g_log.printf("[net] test 3/5, HTTPS vers %s : %d en %u ms (code > 0 = TLS fonctionnel)\n", NET_DIAG_HTTPS_URL, code, (unsigned)elapsed);
    if (code > 0)
    {
        g_log.println("[net] verdict : le TLS fonctionne vers un autre site, le blocage est propre au serveur de l'API");
        return;
    }

    if (!g_pauseBleDuringTls)
    {
        g_pauseBleDuringTls = true;
        code = httpsProbe(elapsed);
        g_log.printf("[net] test 4/5, HTTPS avec publicité BLE suspendue : %d en %u ms\n", code, (unsigned)elapsed);
        if (code > 0)
        {
            g_log.println("[net] verdict : le BLE perturbait le TLS — publicité BLE désormais suspendue pendant chaque connexion à l'API");
            return;
        }
        g_pauseBleDuringTls = false;
    }

    WiFi.setTxPower(WIFI_POWER_8_5dBm);
    code = httpsProbe(elapsed);
    g_log.printf("[net] test 5/5, HTTPS avec puissance WiFi réduite (8,5 dBm) : %d en %u ms\n", code, (unsigned)elapsed);
    if (code > 0)
    {
        g_log.println("[net] verdict : l'alimentation ne tient pas les pics d'émission — puissance WiFi réduite conservée");
        return;
    }
    WiFi.setTxPower(WIFI_POWER_19_5dBm);
    g_log.println("[net] verdict : TLS impossible même sans BLE et à puissance réduite (voir les tests 1 et 2)");
}

/**
 * Tâche dédiée (pile 16 Ko) pour la synchro périodique : la poignée de main
 * TLS de mbedTLS (HTTPS vers l'API) a besoin de plus de pile que celle de la
 * tâche loop() par défaut — l'y exécuter directement provoquait un
 * débordement de pile sur esp32_borne/, même cause ici. Se réveille au
 * rythme de la plus courte des cadences : synchro des scans
 * (SYNC_INTERVAL_MS), envoi des journaux (LOG_FLUSH_INTERVAL_MS) et
 * vérification OTA (OTA_CHECK_INTERVAL_MS, plus une dès la première
 * connexion) gardent chacune leur propre minuterie. Les trois s'enchaînent
 * dans cette seule tâche : jamais deux connexions TLS simultanées.
 */
static void syncTask(void *)
{
    const uint32_t tickMs = REMOTE_LOG_ENABLED ? std::min(SYNC_INTERVAL_MS, LOG_FLUSH_INTERVAL_MS) : SYNC_INTERVAL_MS;
    uint32_t lastSync = millis();
    uint32_t syncInterval = SYNC_INTERVAL_MS;
    uint32_t lastLogFlush = millis();
    uint32_t logInterval = LOG_FLUSH_INTERVAL_MS;
    uint32_t lastMemoryLog = millis();
    uint32_t lastDiagnostic = 0;
    uint32_t lastOtaCheck = 0;
    bool otaCheckedOnce = false;
    for (;;)
    {
        vTaskDelay(pdMS_TO_TICKS(tickMs));
        if (WiFi.status() != WL_CONNECTED)
            continue;

        // Après un échec, la tentative suivante attend SYNC_RETRY_INTERVAL_MS
        // (compté à partir de la FIN de la tentative ratée, qui peut elle-même
        // durer jusqu'à ~30 s de délais de connexion).
        if (millis() - lastSync >= syncInterval)
        {
            const bool synced = syncWithApi();
            if (!synced && (lastDiagnostic == 0 || millis() - lastDiagnostic >= NET_DIAG_INTERVAL_MS))
            {
                diagnoseConnectivity();
                lastDiagnostic = millis();
            }
            lastSync = millis();
            syncInterval = synced ? SYNC_INTERVAL_MS : SYNC_RETRY_INTERVAL_MS;
        }
        if (millis() - lastLogFlush >= logInterval)
        {
            lastLogFlush = millis();
            logInterval = flushRemoteLogs() ? LOG_FLUSH_INTERVAL_MS : LOG_RETRY_INTERVAL_MS;
        }
        if ((!otaCheckedOnce || millis() - lastOtaCheck >= OTA_CHECK_INTERVAL_MS) && otaSafeToRun())
        {
            otaCheckedOnce = true;
            lastOtaCheck = millis();
            checkForUpdate();
        }
        if (millis() - lastMemoryLog >= MEMORY_LOG_INTERVAL_MS)
        {
            lastMemoryLog = millis();
            logMemory("suivi");
        }

        // Dernier recours : si, malgré la réserve, le TLS échoue plusieurs
        // fois de suite faute de mémoire, seul un redémarrage rend un tas
        // propre. Sans risque pour les pointages (file sur SD/flash), et
        // jamais pendant qu'un téléphone est connecté.
        if (g_tlsMemoryFailures >= TLS_MEMORY_FAILURES_BEFORE_RESTART && otaSafeToRun())
        {
            g_log.println("[mem] mémoire trop morcelée pour le TLS, redémarrage de la borne (file conservée)");
            delay(300);
            ESP.restart();
        }
    }
}

// ---------------------------------------------------------------------------
// Traitement d'un scan — commun aux transports BLE (principal) et HTTP
// (conservé pour du débogage via curl sur le WiFi STA déjà utilisé pour la
// synchro, ex. `curl http://<ip-sta>/scan`).
// ---------------------------------------------------------------------------

/**
 * { "type": "scan"|"admin_proxy", "teacher_token": "...", "payload": {...}, "captured_at"?: "ISO8601" }
 *
 * Le téléphone transmet exactement ce qu'il enverrait à l'API en ligne
 * (mêmes champs `payload` : qr_code[/enseignant_id/motif]). La borne ajoute
 * local_id + captured_at + un `payload.bssid` (BSSID WiFi si fourni par
 * l'appelant HTTP legacy, sinon l'adresse BLE de la borne — preuve de
 * proximité) + `payload.photo_base64` si un selfie a été transmis (voir
 * ScanCharCallbacks/BLE_TAG_*), écrit sur flash, puis répond — l'envoi vers
 * l'API est différé au prochain cycle de `syncWithApi()`.
 *
 * `photo` : selfie JPEG brut reçu par chunks BLE (vide si aucun). Il n'est
 * jamais copié dans le document JSON : seul un marqueur y est posé, remplacé
 * par le base64 au moment de l'écriture (voir appendToQueue).
 */
static String processScan(const String &rawJson, const std::vector<uint8_t> &photo)
{
    if (queueLength() >= queueCapacity())
    {
        return "{\"error\":\"file locale saturée, réessayez plus tard\"}";
    }

    // Documents dimensionnés sur le contenu réel (un scan sans selfie pèse
    // ~200 octets) et non sur le pire cas : voir le commentaire plus bas.
    DynamicJsonDocument in(rawJson.length() + 1024);
    if (in.capacity() == 0)
    {
        g_log.printf("[queue] mémoire insuffisante (bloc libre max %u o), scan refusé\n", (unsigned)ESP.getMaxAllocHeap());
        return "{\"error\":\"borne momentanément saturée, réessayez\"}";
    }
    if (deserializeJson(in, rawJson) != DeserializationError::Ok)
    {
        return "{\"error\":\"JSON invalide\"}";
    }

    const char *type = in["type"] | "";
    if (strcmp(type, "scan") != 0 && strcmp(type, "admin_proxy") != 0)
    {
        return "{\"error\":\"type invalide\"}";
    }
    if (!in.containsKey("teacher_token") || in["teacher_token"].as<String>().length() == 0 || !in.containsKey("payload") || !in["payload"].is<JsonObject>())
    {
        return "{\"error\":\"teacher_token et payload requis\"}";
    }
    if (!in["payload"].containsKey("qr_code") || in["payload"]["qr_code"].as<String>().length() == 0)
    {
        return "{\"error\":\"payload.qr_code requis\"}";
    }

    String capturedAt;
    if (in.containsKey("captured_at"))
    {
        capturedAt = in["captured_at"].as<String>();
    }
    if (capturedAt.length() == 0)
    {
        if (g_time_ready)
        {
            // Cet ESP32 a son propre accès au modem : il fait son propre NTP, pas
            // besoin de synchro horaire par un second module.
            time_t now;
            time(&now);
            struct tm tmVal;
            gmtime_r(&now, &tmVal);
            char buf[25];
            strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%SZ", &tmVal);
            capturedAt = String(buf);
        }
        else
        {
            return "{\"error\":\"borne non synchronisée en heure, réessayez dans un instant\"}";
        }
    }

    char localId[40];
    makeLocalId(localId, sizeof(localId));

    // ArduinoJson n'échoue pas bruyamment : si le tas (fragmenté par BLE + TLS,
    // pas de PSRAM ici) n'a pas le bloc demandé d'un seul tenant, le document
    // a une capacité nulle, toutes les écritures ci-dessous sont ignorées et
    // la sérialisation produit "null". Ce "null" était écrit dans la file et
    // le téléphone recevait queued=true : scan perdu, et la ligne bloquait
    // toute la synchro (422 en boucle). On refuse donc explicitement : le
    // téléphone affiche l'erreur et l'enseignant rescanne.
    // Le document ne contient plus le selfie (marqueur seulement) : ~1 Ko
    // suffit là où l'ancienne version exigeait 20 Ko contigus — refusés dès
    // que le plus grand bloc libre tombait à ~10 Ko, même sans photo.
    DynamicJsonDocument out(in.memoryUsage() + 768);
    if (out.capacity() == 0)
    {
        g_log.printf("[queue] mémoire insuffisante (bloc libre max %u o), scan refusé\n", (unsigned)ESP.getMaxAllocHeap());
        return "{\"error\":\"borne momentanément saturée, réessayez\"}";
    }
    out["local_id"] = localId;
    out["type"] = type;
    out["teacher_token"] = in["teacher_token"];
    out["payload"] = in["payload"];
    if (!out["payload"].containsKey("bssid") || out["payload"]["bssid"].as<String>().length() == 0)
    {
        out["payload"]["bssid"] = NimBLEDevice::getAddress().toString();
    }
    out["captured_at"] = capturedAt;
    if (!photo.empty())
    {
        out["payload"]["photo_base64"] = PHOTO_PLACEHOLDER;
    }
    if (out.overflowed())
    {
        g_log.println("[queue] paquet incomplet (document saturé), scan refusé");
        return "{\"error\":\"borne momentanément saturée, réessayez\"}";
    }

    String serialized;
    serializeJson(out, serialized);

    // Écriture sur flash AVANT toute réponse au téléphone ou tentative
    // réseau : c'est ce qui garantit qu'un scan accepté ne se perd jamais,
    // même si l'ESP32 redémarre dans la seconde qui suit. Et pas de
    // queued=true si l'écriture a échoué : le téléphone doit le savoir.
    if (!serialized.startsWith("{") || !appendToQueue(serialized, photo))
    {
        return "{\"error\":\"enregistrement impossible sur la borne, réessayez\"}";
    }

    StaticJsonDocument<160> resp;
    resp["queued"] = true;
    resp["local_id"] = localId;
    resp["photo_captured"] = !photo.empty();
    String respStr;
    serializeJson(resp, respStr);
    return respStr;
}

// ---------------------------------------------------------------------------
// Serveur BLE — reçoit le pointage du téléphone de l'enseignant (transport principal)
// ---------------------------------------------------------------------------

static NimBLECharacteristic *g_bleResultChar = nullptr;

// processScan() écrit sur la flash : trop lent pour tourner dans le callback
// GATT lui-même, qui s'exécute sur la tâche hôte NimBLE. L'y bloquer perturbe
// le timing de la connexion BLE et fait tomber la liaison — même cause que
// sur esp32_borne/. onWrite() se contente donc de recopier la requête et de
// poser un drapeau ; le traitement réel (et la notification du résultat) a
// lieu dans loop(), sur la tâche principale.
static SemaphoreHandle_t g_pendingScanMutex = nullptr;
static String g_pendingScanJson;
// g_pendingScan : déclaré en tête de fichier (lu aussi par otaSafeToRun()).

// Buffer d'accumulation des chunks photo (BLE_TAG_PHOTO_CHUNK) reçus AVANT le
// tag final (BLE_TAG_SCAN_FINAL) — voir ScanCharCallbacks. Transféré vers
// g_pendingScanPhoto à la réception du tag final, pour que loop() dispose
// d'un instantané figé pendant que le téléphone pourrait déjà commencer à
// envoyer les chunks du scan suivant.
static std::vector<uint8_t> g_pendingPhotoBuffer;
static std::vector<uint8_t> g_pendingScanPhoto;

// Buffer d'accumulation des chunks JSON (BLE_TAG_JSON_CHUNK) — même principe
// que g_pendingPhotoBuffer, pour le cas où le JSON final ne tient pas dans un
// seul chunk (MTU bas, voir BLE_TAG_JSON_CHUNK dans config.h).
static std::string g_pendingJsonBuffer;

/** Réinitialise l'état d'assemblage BLE (photo + JSON en cours). À appeler à
 * chaque nouvelle connexion : sans ça, une tentative avortée en plein milieu
 * d'un transfert (déconnexion, retry du téléphone après une erreur GATT)
 * laisserait des chunks orphelins auxquels la tentative suivante viendrait
 * s'ajouter, assemblant une photo/un JSON corrompus sans qu'aucune erreur ne
 * le signale. */
static void resetPendingBleScan()
{
    MutexGuard guard(g_pendingScanMutex);
    g_pendingPhotoBuffer.clear();
    g_pendingJsonBuffer.clear();
    g_pendingScanJson = "";
    g_pendingScan = false;
}

class ScanCharCallbacks : public NimBLECharacteristicCallbacks
{
    void onWrite(NimBLECharacteristic *characteristic) override
    {
        const std::string &value = characteristic->getValue();
        if (value.empty())
            return;

        MutexGuard guard(g_pendingScanMutex);
        const uint8_t tag = static_cast<uint8_t>(value[0]);
        if (tag == BLE_TAG_PHOTO_CHUNK)
        {
            // Au-delà de MAX_PHOTO_BYTES, on tronque silencieusement plutôt que
            // de laisser grossir le buffer sans limite (bug/version app
            // incompatible) : le scan finira par partir avec une photo
            // partielle inutilisable, jamais en bloquant le pointage.
            if (g_pendingPhotoBuffer.size() < MAX_PHOTO_BYTES)
            {
                const size_t room = MAX_PHOTO_BYTES - g_pendingPhotoBuffer.size();
                const size_t toCopy = std::min(value.size() - 1, room);
                g_pendingPhotoBuffer.insert(
                    g_pendingPhotoBuffer.end(),
                    value.begin() + 1,
                    value.begin() + 1 + toCopy);
            }
        }
        else if (tag == BLE_TAG_JSON_CHUNK)
        {
            if (g_pendingJsonBuffer.size() < MAX_JSON_CHUNK_BYTES)
            {
                const size_t room = MAX_JSON_CHUNK_BYTES - g_pendingJsonBuffer.size();
                const size_t toCopy = std::min(value.size() - 1, room);
                g_pendingJsonBuffer.append(value, 1, toCopy);
            }
        }
        else if (tag == BLE_TAG_SCAN_FINAL)
        {
            // Dernier morceau du JSON, éventuellement vide (cas historique :
            // le JSON entier tenait dans cette seule écriture, sans chunk
            // BLE_TAG_JSON_CHUNK préalable).
            if (value.size() > 1 && g_pendingJsonBuffer.size() < MAX_JSON_CHUNK_BYTES)
            {
                const size_t room = MAX_JSON_CHUNK_BYTES - g_pendingJsonBuffer.size();
                const size_t toCopy = std::min(value.size() - 1, room);
                g_pendingJsonBuffer.append(value, 1, toCopy);
            }
            g_pendingScanJson = String(g_pendingJsonBuffer.c_str());
            g_pendingJsonBuffer.clear();
            g_pendingScanPhoto = std::move(g_pendingPhotoBuffer);
            g_pendingPhotoBuffer.clear();
            g_pendingScan = true;
        }
        // Tag inconnu (protocole désynchronisé) : ignoré, le téléphone
        // relancera un scan complet via sa boucle de retry BLE.
    }
};

class BorneServerCallbacks : public NimBLEServerCallbacks
{
    void onConnect(NimBLEServer *pServer) override
    {
        // Voir resetPendingBleScan() : une nouvelle connexion démarre toujours
        // un scan propre, jamais la suite d'une tentative précédente avortée.
        resetPendingBleScan();
    }
};

/** Appelé depuis loop() : traite la requête en attente (s'il y en a une) hors du callback GATT. */
static void processPendingBleScan()
{
    if (!g_pendingScan)
        return;

    String rawJson;
    std::vector<uint8_t> photoBytes;
    {
        MutexGuard guard(g_pendingScanMutex);
        rawJson = g_pendingScanJson;
        photoBytes = std::move(g_pendingScanPhoto);
        g_pendingScanPhoto.clear();
        g_pendingScan = false;
    }

    beepBuzzer();
    g_log.printf("[ble] scan reçu: json=%uo photo=%uo\n", (unsigned)rawJson.length(), (unsigned)photoBytes.size());
    String response = processScan(rawJson, photoBytes);
    if (g_bleResultChar)
    {
        g_bleResultChar->setValue(response);
        g_bleResultChar->notify();
    }
}

static void setupBle()
{
    NimBLEDevice::init(BLE_DEVICE_NAME);

    // TX power par défaut de NimBLE-Arduino (~+9dbm) trop élevée pour
    // l'alimentation USB de ce banc de test : le pic de courant du démarrage
    // radio (init + advertising) déclenchait le détecteur de brownout en
    // boucle — confirmé en comparant avec un firmware sans BLE, qui démarre
    // sans souci sur le même câble/port. Réglé à N6 ; à redescendre encore
    // (N9/N12) si le brownout persiste.
    NimBLEDevice::setPower(ESP_PWR_LVL_N6);

    NimBLEServer *bleServer = NimBLEDevice::createServer();
    bleServer->setCallbacks(new BorneServerCallbacks());
    NimBLEService *service = bleServer->createService(BLE_SERVICE_UUID);

    NimBLECharacteristic *scanChar = service->createCharacteristic(
        BLE_CHAR_SCAN_UUID, NIMBLE_PROPERTY::WRITE);
    scanChar->setCallbacks(new ScanCharCallbacks());

    g_bleResultChar = service->createCharacteristic(
        BLE_CHAR_RESULT_UUID, NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY);

    service->start();
    g_ble_address = NimBLEDevice::getAddress().toString().c_str();

    // Un paquet d'advertising BLE "legacy" ne fait que 31 octets : le nom
    // (BLE_DEVICE_NAME) + l'UUID de service 128-bit ne tiennent pas ensemble
    // dans ce budget. Laissé au comportement par défaut de NimBLE, l'UUID de
    // service se retrouve alors relégué dans le scan response — or certains
    // téléphones bas de gamme (chipsets MediaTek/Unisoc, ex. Transsion
    // Tecno/Infinix/Itel) appliquent leur filtre BLE natif par UUID
    // uniquement sur le paquet d'advertising primaire et ignorent le scan
    // response : la borne devient invisible pour ces téléphones (scan
    // toujours vide, timeout côté app). On force donc explicitement l'UUID
    // de service dans le paquet primaire (3 + 18 octets, largement sous 31)
    // et on relègue le nom — cosmétique, voir config.h — au scan response.
    NimBLEAdvertisementData advData;
    advData.setFlags(BLE_HS_ADV_F_DISC_GEN | BLE_HS_ADV_F_BREDR_UNSUP);
    advData.setCompleteServices(NimBLEUUID(BLE_SERVICE_UUID));

    NimBLEAdvertisementData scanResponseData;
    scanResponseData.setName(BLE_DEVICE_NAME);

    NimBLEAdvertising *advertising = NimBLEDevice::getAdvertising();
    advertising->setAdvertisementData(advData);
    advertising->setScanResponseData(scanResponseData);
    advertising->start();

    g_log.printf("[ble] serveur démarré, adresse=%s\n", NimBLEDevice::getAddress().toString().c_str());
}

// ---------------------------------------------------------------------------
// Serveur HTTP local — legacy/débogage uniquement (voir processScan ci-dessus)
// ---------------------------------------------------------------------------

static void handleScan()
{
    if (!server.hasArg("plain"))
    {
        server.send(400, "application/json", "{\"error\":\"corps JSON manquant\"}");
        return;
    }
    // Chemin de débogage uniquement (curl) : pas de selfie possible ici, voir
    // le commentaire au-dessus de handleScan().
    static const std::vector<uint8_t> noPhoto;
    String resp = processScan(server.arg("plain"), noPhoto);
    bool queued = resp.indexOf("\"queued\"") >= 0;
    server.send(queued ? 202 : 400, "application/json", resp);
}

static void handleNotFound()
{
    server.send(404, "application/json", "{\"error\":\"not found\"}");
}

// ---------------------------------------------------------------------------

static void connectWifiIfNeeded()
{
    if (WiFi.status() == WL_CONNECTED)
        return;

    static uint32_t lastAttempt = 0;
    if (millis() - lastAttempt < 10000)
        return;
    lastAttempt = millis();

    g_log.println("[wifi] tentative de connexion au modem...");
    WiFi.begin(STA_SSID, STA_PASSWORD);
}

static void onWifiConnected()
{
    g_log.printf("[wifi] connecté au modem, IP=%s\n", WiFi.localIP().toString().c_str());

    configTime(0, 0, NTP_SERVER);
    struct tm timeinfo;
    if (getLocalTime(&timeinfo, 5000))
    {
        g_time_ready = true;
        g_log.println("[time] NTP synchronisé");
    }
}

void setup()
{
    // Détecteur de brownout matériel désactivé : diagnostic ci-dessus
    // (delay(1000) + TX power N6) montre que le brownout persiste à
    // l'identique malgré ces réglages, ce qui pointe vers une cause
    // matérielle (alim/câble USB) plutôt que logicielle. Cette désactivation
    // n'est qu'une mesure temporaire pour voir si le boot va au bout malgré
    // la sous-tension — elle ne corrige pas la cause et devrait être retirée
    // une fois l'alimentation fiabilisée (voir commentaires plus bas sur le
    // delay et la TX power).
    WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);

    Serial.begin(115200);
    g_log.begin();

    pinMode(BUZZER_GPIO, OUTPUT);
    digitalWrite(BUZZER_GPIO, LOW);

    g_fsMutex = xSemaphoreCreateMutex();
    g_pendingScanMutex = xSemaphoreCreateMutex();

    // Cause du dernier redémarrage : permet de repérer depuis le backoffice
    // un brownout (voir ci-dessus) ou un crash (panic/watchdog) sans câble USB.
    g_log.printf("[boot] firmware %s, cause du reset=%d (1=mise sous tension, 3=redémarrage logiciel, 4=panic, 5-7=watchdog, 9=brownout)\n",
                 FIRMWARE_VERSION, (int)esp_reset_reason());

    setupStorage();
    recoverQueueRewrite();
    g_log.printf("[queue] %u paquet(s) en attente au démarrage\n", (unsigned)queueLength());

    // Laisse le régulateur 3.3V se stabiliser après la séquence de boot
    // (lecture flash + montage LittleFS) avant la première activité radio :
    // sur ce module (DevKit générique, pas de gros condensateur de découplage
    // ajouté), le pic de courant du démarrage BLE juste après le boot a
    // déclenché des reset en boucle par le détecteur de brownout ("Brownout
    // detector was triggered"). Ce délai réduit le risque en évitant de faire
    // chevaucher ce pic avec la fin de la séquence de boot flash — la vraie
    // cause reste probablement l'alimentation USB (câble/port) sur ce banc de
    // test, à vérifier si le problème persiste.
    delay(1000);

    // WiFi en client uniquement : le téléphone parle en BLE, plus besoin du
    // rôle AP (voir setupBle() ci-dessous). Mode réglé ici (pas encore de
    // trafic radio significatif) mais WiFi.begin() est décalé après l'init
    // BLE (voir plus bas) pour étaler les pics de courant plutôt que de les
    // cumuler, même précaution que sur esp32_borne/.
    WiFi.mode(WIFI_STA);

    setupBle();

    delay(1500);
    g_log.println("[wifi] tentative de connexion au modem...");
    WiFi.begin(STA_SSID, STA_PASSWORD);

    // Cœur 1 (APP_CPU), comme loopTask par défaut sur Arduino-ESP32 — la pile
    // dédiée de 16 Ko est la partie qui compte ici, pas l'affinité de cœur.
    // BLE et pilote WiFi sont initialisés, le tas est encore propre : c'est le
    // moment de mettre de côté la mémoire du TLS (voir reserveTlsMemory()).
    reserveTlsMemory();
    logMemory("démarrage, réserve TLS constituée");

    xTaskCreatePinnedToCore(syncTask, "sync_task", 16384, nullptr, 1, nullptr, 1);
}

void loop()
{
    processPendingBleScan();

    static bool wasConnected = false;
    bool isConnected = WiFi.status() == WL_CONNECTED;
    if (isConnected && !wasConnected)
        onWifiConnected();
    wasConnected = isConnected;

    if (!isConnected)
        connectWifiIfNeeded();

    // La synchro périodique tourne dans sa propre tâche (syncTask, voir
    // setup()) — pile dédiée assez grande pour la poignée de main TLS.

    delay(10);

    // Réaffiche l'adresse BLE dès le premier tour de loop() puis toutes les 3s
    // (à saisir dans le backoffice, Appareils & points d'accès > Bornes WiFi,
    // champ BSSID) : le port série "hoquette" parfois juste après le boot et
    // fait rater l'unique ligne imprimée au démarrage. Serial direct : déjà
    // remontée une fois au backoffice par setupBle(), inutile d'y noyer le
    // moniteur à distance sous une ligne identique toutes les 3 s.
    static uint32_t lastBleAddrPrint = 0;
    static bool bleAddrPrintedOnce = false;
    if (!bleAddrPrintedOnce || millis() - lastBleAddrPrint > 3000)
    {
        bleAddrPrintedOnce = true;
        lastBleAddrPrint = millis();
        Serial.print("[ble] adresse=");
        Serial.println(g_ble_address);
    }
}
