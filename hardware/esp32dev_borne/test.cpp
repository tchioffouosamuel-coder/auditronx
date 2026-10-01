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
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// Types déclarés AVANT toute fonction, juste après les #include : l'IDE
// Arduino insère automatiquement les prototypes des fonctions juste avant la
// première fonction du fichier (y compris celle générée par la macro
// SET_LOOP_TASK_STACK_SIZE ci-dessous), et ces prototypes doivent déjà
// connaître les types de leurs paramètres.
struct QueuedPacket
{
    String local_id;
    String raw_json;
};

struct LogLine
{
    uint32_t seq;
    uint32_t uptime_ms;
    String message;
};

// Versions majeures attendues : les suivantes ont changé d'API (signatures des
// callbacks BLE, documents JSON) et ne compilent pas avec ce code.
#if ARDUINOJSON_VERSION_MAJOR != 6
#error "Installez ArduinoJson 6.21.x (Benoit Blanchon) — la version 7 n'est pas compatible."
#endif
#if !__has_include(<NimBLESecurity.h>)
#error "Installez NimBLE-Arduino 1.4.3 (h2zero) — la version 2.x n'est pas compatible."
#endif

// Équivalent Arduino IDE de -DCONFIG_ARDUINO_LOOP_STACK_SIZE=16384
// (platformio.ini) : pile de loop() assez grande pour TLS + buffers JSON.
SET_LOOP_TASK_STACK_SIZE(16 * 1024);

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

// Un seul paquet par requête : la négociation TLS consomme déjà beaucoup de
// RAM sur cet ESP32 sans PSRAM, et chaque paquet peut contenir un selfie.
inline constexpr size_t SYNC_BATCH_SIZE = 1;

// Capacité des documents ArduinoJson dynamiques (RAM) par paquet : JPEG
// ~160x120 qualité ~20 (quelques Ko) encodé en base64 (x1.37) + payload
// qr_code/enseignant_id/motif + token + entêtes JSON, avec marge.
// Capacité suffisante pour un selfie JPEG basse résolution (~160x120, qualité ~20)
// encodé en base64 + le JSON du scan et ses métadonnées. Le seuil historique de
// 10 Ko était trop juste et faisait tomber silencieusement photo_base64 quand le
// paquet dépassait cette capacité, sans que le téléphone le remarque.
inline constexpr size_t PACKET_JSON_CAPACITY = 20 * 1024;
inline constexpr size_t SYNC_BODY_JSON_CAPACITY = SYNC_BATCH_SIZE * PACKET_JSON_CAPACITY;

// Cadence de vérification de la connectivité / tentative de synchro.
inline constexpr uint32_t SYNC_INTERVAL_MS = 15000;

// ---- Moniteur série à distance (backoffice > Moniteur des bornes) ----
// Chaque ligne imprimée sur le port série est aussi gardée dans un tampon RAM
// et poussée vers POST /api/relay/logs. Sans internet, les lignes les plus
// anciennes sont écrasées au-delà de LOG_BUFFER_MAX_LINES : diagnostic
// uniquement, rien de critique n'y transite (contrairement à la file de scans).
inline constexpr bool REMOTE_LOG_ENABLED = true;
inline constexpr char API_RELAY_LOGS_PATH[] = "/api/relay/logs";
inline constexpr uint32_t LOG_FLUSH_INTERVAL_MS = 5000;
inline constexpr size_t LOG_BUFFER_MAX_LINES = 80;
inline constexpr size_t LOG_LINE_MAX_LEN = 240;
inline constexpr size_t LOG_BATCH_MAX_LINES = 30;

inline constexpr char NTP_SERVER[] = "pool.ntp.org";
// ===== Fin configuration =====

static WebServer server(80);
static uint32_t g_local_id_counter = 0;
static bool g_time_ready = false;
static String g_ble_address;

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
    size_t n = 0;
    while (f.available())
    {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() > 0)
            n++;
    }
    f.close();
    return n;
}

static void appendToQueue(const String &json)
{
    MutexGuard guard(g_fsMutex);
    File f = g_queueFs->open(QUEUE_FILE, "a", true);
    if (!f)
    {
        g_log.println("[queue] échec ouverture flash en écriture");
        return;
    }
    f.println(json);
    f.close();
}

static bool readQueueBatch(std::vector<QueuedPacket> &out)
{
    MutexGuard guard(g_fsMutex);
    if (!g_queueFs->exists(QUEUE_FILE))
        return true;

    File f = g_queueFs->open(QUEUE_FILE, "r");
    if (!f)
        return false;

    while (f.available() && out.size() < SYNC_BATCH_SIZE)
    {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() == 0)
            continue;

        DynamicJsonDocument doc(PACKET_JSON_CAPACITY);
        if (deserializeJson(doc, line) != DeserializationError::Ok)
            continue;

        QueuedPacket p;
        p.local_id = doc["local_id"].as<String>();
        p.raw_json = line;
        out.push_back(p);
    }
    f.close();
    return true;
}

/** Réécrit la file sans les local_id passés en paramètre (terminaux : ok ou rejected). */
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

    String kept;
    while (in.available())
    {
        String line = in.readStringUntil('\n');
        String trimmed = line;
        trimmed.trim();
        if (trimmed.length() == 0)
            continue;

        DynamicJsonDocument doc(PACKET_JSON_CAPACITY);
        if (deserializeJson(doc, trimmed) != DeserializationError::Ok)
        {
            kept += trimmed;
            kept += '\n';
            continue;
        }

        String id = doc["local_id"].as<String>();
        bool remove = false;
        for (const auto &rid : idsToRemove)
        {
            if (rid == id)
            {
                remove = true;
                break;
            }
        }
        if (!remove)
        {
            kept += trimmed;
            kept += '\n';
        }
    }
    in.close();

    File out = g_queueFs->open(QUEUE_FILE, "w");
    if (!out)
        return;
    out.print(kept);
    out.close();
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
        secureClientReady = true;
    }
    return secureClient;
}

static void syncWithApi()
{
    if (WiFi.status() != WL_CONNECTED)
        return;

    std::vector<QueuedPacket> batch;
    if (!readQueueBatch(batch) || batch.empty())
        return;

    String payload;
    payload.reserve(12 + batch.size() * 256);
    payload = "{\"packets\":[";
    for (size_t i = 0; i < batch.size(); i++)
    {
        if (i > 0)
            payload += ',';
        payload += batch[i].raw_json;
    }
    payload += "]}";
    const size_t batchCount = batch.size();
    batch.clear();
    batch.shrink_to_fit();
    if (payload.length() <= 12)
    {
        g_log.println("[sync] corps JSON vide, on retentera au prochain cycle");
        return;
    }

    HTTPClient http;
    String url = String(API_BASE_URL) + API_RELAY_SYNC_PATH;
    http.begin(apiClient(), url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.setTimeout(20000);

    int status = http.POST(payload);
    if (status != 200)
    {
        String errorBody = http.getString();
        g_log.printf("[sync] échec HTTP %d: %s, on retentera au prochain cycle\n", status, errorBody.c_str());
        http.end();
        return; // rien n'est retiré de la file : nouvelle tentative plus tard
    }

    String respBody = http.getString();
    http.end();

    StaticJsonDocument<4096> resp;
    if (deserializeJson(resp, respBody) != DeserializationError::Ok)
    {
        g_log.println("[sync] réponse API illisible, on retentera au prochain cycle");
        return;
    }

    std::vector<String> toRemove;
    for (JsonObject result : resp["results"].as<JsonArray>())
    {
        const char *status_ = result["status"] | "";
        const char *localId = result["local_id"] | "";
        // "ok" (accepté) et "rejected" (invalide, inutile de réessayer) sont
        // terminaux : on purge. "retry" reste en file pour le prochain cycle.
        if (strcmp(status_, "ok") == 0 || strcmp(status_, "rejected") == 0)
        {
            toRemove.push_back(String(localId));
        }
    }

    removeFromQueue(toRemove);
    g_log.printf("[sync] %u paquet(s) envoyés, %u confirmé(s)/rejeté(s)\n", (unsigned)batchCount, (unsigned)toRemove.size());
}

/**
 * Pousse vers l'API les lignes du moniteur série en attente (voir
 * RemoteLogger). Les échecs ne sont imprimés que sur Serial : les passer par
 * g_log alimenterait le tampon avec ses propres erreurs d'envoi.
 */
static void flushRemoteLogs()
{
    if (!REMOTE_LOG_ENABLED || WiFi.status() != WL_CONNECTED)
        return;

    std::vector<LogLine> batch;
    g_log.snapshot(batch, LOG_BATCH_MAX_LINES);
    if (batch.empty())
        return;

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

    HTTPClient http;
    http.begin(apiClient(), String(API_BASE_URL) + API_RELAY_LOGS_PATH);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.setTimeout(10000);
    const int status = http.POST(body);
    http.end();

    if (status >= 200 && status < 300)
    {
        g_log.ack(lastSeq);
    }
    else if (status >= 400 && status < 500 && status != 429)
    {
        // Lot refusé tel quel (validation, token révoqué...) : il ne passera
        // jamais, on le jette plutôt que de bloquer les lignes suivantes.
        Serial.printf("[log] lot refusé par l'API (HTTP %d), ignoré\n", status);
        g_log.ack(lastSeq);
    }
    else
    {
        Serial.printf("[log] échec envoi (HTTP %d), nouvelle tentative plus tard\n", status);
    }
}

/**
 * Tâche dédiée (pile 16 Ko) pour la synchro périodique : la poignée de main
 * TLS de mbedTLS (HTTPS vers l'API) a besoin de plus de pile que celle de la
 * tâche loop() par défaut — l'y exécuter directement provoquait un
 * débordement de pile sur esp32_borne/, même cause ici. Rythmée par
 * LOG_FLUSH_INTERVAL_MS (moniteur à distance quasi temps réel), la synchro
 * des scans gardant sa propre cadence SYNC_INTERVAL_MS.
 */
static void syncTask(void *)
{
    uint32_t lastSync = millis();
    for (;;)
    {
        vTaskDelay(pdMS_TO_TICKS(REMOTE_LOG_ENABLED ? LOG_FLUSH_INTERVAL_MS : SYNC_INTERVAL_MS));
        if (WiFi.status() != WL_CONNECTED)
            continue;
        if (millis() - lastSync >= SYNC_INTERVAL_MS)
        {
            lastSync = millis();
            syncWithApi();
        }
        flushRemoteLogs();
    }
}

// ---------------------------------------------------------------------------
// Traitement d'un scan — commun aux transports BLE (principal) et HTTP
// (conservé pour du débogage via curl sur le WiFi STA déjà utilisé pour la
// synchro, ex. `curl http://<ip-sta>/scan`).
// ---------------------------------------------------------------------------

/** Encode en base64 le selfie reçu par chunks BLE (voir ScanCharCallbacks) ;
 * chaîne vide si aucune photo n'a été transmise pour ce scan. Même motif que
 * capturePhotoBase64() sur esp32_borne/, mais à partir d'un buffer déjà en
 * mémoire (le téléphone capture et compresse la photo, pas la borne). */
static String encodePhotoBase64(const std::vector<uint8_t> &raw)
{
    if (raw.empty())
        return "";

    size_t encodedLen = 0;
    mbedtls_base64_encode(nullptr, 0, &encodedLen, raw.data(), raw.size());

    std::vector<unsigned char> buf(encodedLen);
    size_t written = 0;
    int rc = mbedtls_base64_encode(buf.data(), buf.size(), &written, raw.data(), raw.size());
    if (rc != 0)
    {
        g_log.println("[ble] échec encodage base64 du selfie");
        return "";
    }

    return String(reinterpret_cast<char *>(buf.data()), written);
}

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
 */
static String processScan(const String &rawJson, const String &photoBase64)
{
    if (queueLength() >= queueCapacity())
    {
        return "{\"error\":\"file locale saturée, réessayez plus tard\"}";
    }

    DynamicJsonDocument in(4096);
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

    DynamicJsonDocument out(PACKET_JSON_CAPACITY);
    out["local_id"] = localId;
    out["type"] = type;
    out["teacher_token"] = in["teacher_token"];
    out["payload"] = in["payload"];
    if (!out["payload"].containsKey("bssid") || out["payload"]["bssid"].as<String>().length() == 0)
    {
        out["payload"]["bssid"] = NimBLEDevice::getAddress().toString();
    }
    out["captured_at"] = capturedAt;

    bool photoCaptured = false;
    if (photoBase64.length() > 0)
    {
        out["payload"]["photo_base64"] = photoBase64;
        if (out.overflowed())
        {
            out["payload"].remove("photo_base64");
            g_log.println("[ble] paquet JSON saturé, photo_base64 non enregistrée");
        }
        else
        {
            photoCaptured = true;
        }
    }

    String serialized;
    serializeJson(out, serialized);

    // Écriture sur flash AVANT toute réponse au téléphone ou tentative
    // réseau : c'est ce qui garantit qu'un scan accepté ne se perd jamais,
    // même si l'ESP32 redémarre dans la seconde qui suit.
    appendToQueue(serialized);

    StaticJsonDocument<160> resp;
    resp["queued"] = true;
    resp["local_id"] = localId;
    resp["photo_captured"] = photoCaptured;
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
static volatile bool g_pendingScan = false;

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
    String photoBase64 = encodePhotoBase64(photoBytes);
    g_log.printf("[ble] scan reçu: json=%uo photo=%uo (base64=%uo)\n",
                  (unsigned)rawJson.length(), (unsigned)photoBytes.size(), (unsigned)photoBase64.length());
    String response = processScan(rawJson, photoBase64);
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
    String resp = processScan(server.arg("plain"), "");
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
    g_log.printf("[boot] démarrage, cause du reset=%d (1=mise sous tension, 4=panic, 5-7=watchdog, 9=brownout)\n",
                 (int)esp_reset_reason());

    setupStorage();
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
