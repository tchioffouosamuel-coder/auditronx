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
#include <algorithm>
#include <mbedtls/base64.h>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>
#include <freertos/semphr.h>
#include <NimBLEDevice.h>
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

#include "config.h"

static WebServer server(80);
static uint32_t g_local_id_counter = 0;
static bool g_time_ready = false;

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
        Serial.println("[queue] échec ouverture flash en écriture");
        return;
    }
    f.println(json);
    f.close();
}

struct QueuedPacket
{
    String local_id;
    String raw_json;
};

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
        Serial.println("[fs] échec montage LittleFS");

    g_queueFs = &LittleFS;
    SPI.begin(SD_SCK_GPIO, SD_MISO_GPIO, SD_MOSI_GPIO, SD_CS_GPIO);
    if (!SD.begin(SD_CS_GPIO, SPI, 20000000, "/sdcard", 5, false))
    {
        Serial.println("[sd] carte absente, file LittleFS active");
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
            Serial.println("[sd] ancienne file LittleFS transférée");
        }
        else
        {
            if (source)
                source.close();
            if (destination)
                destination.close();
            Serial.println("[sd] échec transfert de la file LittleFS");
        }
    }

    g_queueFs = &SD;
    g_sd_ready = true;
    Serial.printf("[sd] carte détectée, file SD active (%llu Mo libres)\n",
                  (unsigned long long)((SD.totalBytes() - SD.usedBytes()) / (1024 * 1024)));
}

// ---------------------------------------------------------------------------
// Moteur de pull périodique vers l'API
// ---------------------------------------------------------------------------

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
    if (payload.length() <= 12)
    {
        Serial.println("[sync] corps JSON vide, on retentera au prochain cycle");
        return;
    }

    // Client TLS statique et réutilisé d'un cycle à l'autre (évite l'overhead
    // de reconstruire l'objet WiFiClientSecure à chaque appel ; les buffers
    // mbedTLS eux-mêmes sont (dé)alloués par connect()/stop(), pas par le
    // cycle de vie de cet objet — setBufferSizes() n'existe pas sur cette
    // version du core arduino-esp32 (basée esp-idf 5, buffers fixes).
    static WiFiClientSecure secureClient;
    static bool secureClientReady = false;
    if (!secureClientReady)
    {
        secureClient.setInsecure(); // pas de CA pinnée, cf. comportement HTTPClient par défaut jusqu'ici
        secureClientReady = true;
    }

    HTTPClient http;
    String url = String(API_BASE_URL) + API_RELAY_SYNC_PATH;
    http.begin(secureClient, url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.setTimeout(20000);

    int status = http.POST(payload);
    if (status != 200)
    {
        String errorBody = http.getString();
        Serial.printf("[sync] échec HTTP %d: %s, on retentera au prochain cycle\n", status, errorBody.c_str());
        http.end();
        return; // rien n'est retiré de la file : nouvelle tentative plus tard
    }

    String respBody = http.getString();
    http.end();

    StaticJsonDocument<4096> resp;
    if (deserializeJson(resp, respBody) != DeserializationError::Ok)
    {
        Serial.println("[sync] réponse API illisible, on retentera au prochain cycle");
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
    Serial.printf("[sync] %u paquet(s) envoyés, %u confirmé(s)/rejeté(s)\n", (unsigned)batch.size(), (unsigned)toRemove.size());
}

/**
 * Tâche dédiée (pile 16 Ko) pour la synchro périodique : la poignée de main
 * TLS de mbedTLS (HTTPS vers l'API) a besoin de plus de pile que celle de la
 * tâche loop() par défaut — l'y exécuter directement provoquait un
 * débordement de pile sur esp32_borne/, même cause ici.
 */
static void syncTask(void *)
{
    for (;;)
    {
        vTaskDelay(pdMS_TO_TICKS(SYNC_INTERVAL_MS));
        if (WiFi.status() == WL_CONNECTED)
            syncWithApi();
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
        Serial.println("[ble] échec encodage base64 du selfie");
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
    if (photoBase64.length() > 0)
    {
        out["payload"]["photo_base64"] = photoBase64;
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
    resp["photo_captured"] = photoBase64.length() > 0;
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

    String photoBase64 = encodePhotoBase64(photoBytes);
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

    Serial.print("[ble] serveur démarré, adresse=");
    Serial.println(NimBLEDevice::getAddress().toString().c_str());
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

    Serial.println("[wifi] tentative de connexion au modem...");
    WiFi.begin(STA_SSID, STA_PASSWORD);
}

static void onWifiConnected()
{
    Serial.print("[wifi] connecté au modem, IP=");
    Serial.println(WiFi.localIP());

    configTime(0, 0, NTP_SERVER);
    struct tm timeinfo;
    if (getLocalTime(&timeinfo, 5000))
    {
        g_time_ready = true;
        Serial.println("[time] NTP synchronisé");
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

    setupStorage();
    Serial.printf("[queue] %u paquet(s) en attente au démarrage\n", (unsigned)queueLength());

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
    Serial.println("[wifi] tentative de connexion au modem...");
    WiFi.begin(STA_SSID, STA_PASSWORD);

    // Conservé pour du débogage (curl direct sur l'IP STA) — le téléphone
    // n'utilise plus ce chemin, voir processScan()/setupBle().
    server.on("/scan", HTTP_POST, handleScan);
    server.onNotFound(handleNotFound);
    server.begin();
    Serial.println("[http] serveur local démarré sur le port 80 (débogage)");

    g_fsMutex = xSemaphoreCreateMutex();
    g_pendingScanMutex = xSemaphoreCreateMutex();
    // Cœur 1 (APP_CPU), comme loopTask par défaut sur Arduino-ESP32 — la pile
    // dédiée de 16 Ko est la partie qui compte ici, pas l'affinité de cœur.
    xTaskCreatePinnedToCore(syncTask, "sync_task", 16384, nullptr, 1, nullptr, 1);
}

void loop()
{
    server.handleClient();
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

    // Réaffiche l'adresse BLE dès le premier tour de loop() puis toutes les 3s
    // (à saisir dans le backoffice, Appareils & points d'accès > Bornes WiFi,
    // champ BSSID) : le port série "hoquette" parfois juste après le boot et
    // fait rater l'unique ligne imprimée au démarrage.
    static uint32_t lastBleAddrPrint = 0;
    static bool bleAddrPrintedOnce = false;
    if (!bleAddrPrintedOnce || millis() - lastBleAddrPrint > 3000)
    {
        bleAddrPrintedOnce = true;
        lastBleAddrPrint = millis();
        Serial.print("[ble] adresse=");
        Serial.println(NimBLEDevice::getAddress().toString().c_str());
        Serial.flush();
    }
}