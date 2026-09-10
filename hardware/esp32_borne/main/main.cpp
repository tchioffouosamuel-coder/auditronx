/**
 * ESP32-S3 "borne" (module unique, caméra OV5640) — serveur BLE local pour le
 * téléphone de l'enseignant ET client WiFi (STA) connecté au modem pour la
 * synchro API. Colocalisé avec le modem : un seul module, pas de saut
 * ESP-NOW intermédiaire.
 *
 * Le téléphone parle en BLE (pas WiFi local : négociation trop lente, voir
 * §hardware) — reçoit le pointage via une caractéristique BLE, prend une
 * photo avec la caméra au même instant (preuve visuelle anti-fraude : la
 * personne qui a réellement scanné, pas seulement le téléphone authentifié),
 * écrit le tout immédiatement sur la carte SD avant toute tentative réseau,
 * puis un moteur de pull périodique le pousse vers l'API dès qu'internet est
 * disponible. Un paquet n'est retiré de la file locale que sur confirmation
 * explicite de l'API (`ok` ou `rejected`) — jamais avant, pour ne rien perdre
 * en cas de coupure secteur ou réseau.
 */
#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <FS.h>
#include <SD_MMC.h>
#include <ArduinoJson.h>
#include <esp_camera.h>
#include <mbedtls/base64.h>
#include <time.h>
#include <vector>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>
#include <freertos/semphr.h>

#include <BLEDevice.h>
#include <BLEServer.h>
#include <BLEUtils.h>
#include <algorithm>

#include "config.h"
#include "face_engine.h"
#include "face_cache.h"

static WebServer server(80);
static uint32_t g_local_id_counter = 0;
static bool g_time_ready = false;
static bool g_camera_ready = false;
static volatile bool g_qrScanInProgress = false;
static volatile bool g_hw201Armed = true;
static SemaphoreHandle_t g_apiMutex = nullptr;

static bool hw201IsHigh()
{
    return digitalRead(HW201_GPIO) == LOW;
}

static bool shouldRunRecognition()
{
    return hw201IsHigh() && !g_qrScanInProgress;
}

/** Horodatage ISO8601 (UTC) à partir de l'heure NTP propre de la borne, ou chaîne vide si pas encore synchronisée. */
static String currentIsoTimestamp()
{
    if (!g_time_ready)
        return "";
    time_t now;
    time(&now);
    struct tm tmVal;
    gmtime_r(&now, &tmVal);
    char buf[25];
    strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%SZ", &tmVal);
    return String(buf);
}

// Déclarée ici (utilisée par processScan() pour notifier avant la capture
// photo, voir plus bas) ; assignée dans setupBle().
static BLECharacteristic *g_bleResultChar = nullptr;

static void makeLocalId(char *out, size_t outLen)
{
    snprintf(out, outLen, "borne-%lu-%lu", (unsigned long)millis(), (unsigned long)(++g_local_id_counter));
}

/** RAII pour un sémaphore FreeRTOS — évite d'oublier un xSemaphoreGive() sur un retour anticipé. */
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
// Caméra OV5640
// ---------------------------------------------------------------------------

static bool initCamera()
{
    camera_config_t config{};
    config.ledc_channel = LEDC_CHANNEL_0;
    config.ledc_timer = LEDC_TIMER_0;
    config.pin_d0 = CAMERA_Y2_GPIO;
    config.pin_d1 = CAMERA_Y3_GPIO;
    config.pin_d2 = CAMERA_Y4_GPIO;
    config.pin_d3 = CAMERA_Y5_GPIO;
    config.pin_d4 = CAMERA_Y6_GPIO;
    config.pin_d5 = CAMERA_Y7_GPIO;
    config.pin_d6 = CAMERA_Y8_GPIO;
    config.pin_d7 = CAMERA_Y9_GPIO;
    config.pin_xclk = CAMERA_XCLK_GPIO;
    config.pin_pclk = CAMERA_PCLK_GPIO;
    config.pin_vsync = CAMERA_VSYNC_GPIO;
    config.pin_href = CAMERA_HREF_GPIO;
    config.pin_sccb_sda = CAMERA_SIOD_GPIO;
    config.pin_sccb_scl = CAMERA_SIOC_GPIO;
    config.pin_pwdn = CAMERA_PWDN_GPIO;
    config.pin_reset = CAMERA_RESET_GPIO;
    config.xclk_freq_hz = CAMERA_XCLK_FREQ_HZ;
    config.pixel_format = PIXFORMAT_JPEG;
    config.frame_size = CAMERA_FRAME_SIZE;
    config.jpeg_quality = CAMERA_JPEG_QUALITY;
    config.fb_count = 1;
    config.fb_location = CAMERA_FB_IN_PSRAM;
    config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;

    esp_err_t err = esp_camera_init(&config);
    if (err != ESP_OK)
    {
        Serial.printf("[cam] échec init caméra (0x%x)\n", err);
        return false;
    }
    return true;
}

// La boucle de reconnaissance faciale continue (recognitionTask, voir plus
// bas) et processScan() (photo de preuve BLE/QR) grabbent toutes les deux des
// frames caméra — le driver esp_camera n'est pas thread-safe entre tâches
// concurrentes, d'où ce mutex partagé.
static SemaphoreHandle_t g_cameraMutex = nullptr;

/** Encode une frame déjà grabbée en base64 (ne la libère PAS — à la charge de l'appelant). */
static String encodeFbToBase64(camera_fb_t *fb)
{
    size_t encodedLen = 0;
    mbedtls_base64_encode(nullptr, 0, &encodedLen, fb->buf, fb->len);

    std::vector<unsigned char> buf(encodedLen);
    size_t written = 0;
    int rc = mbedtls_base64_encode(buf.data(), buf.size(), &written, fb->buf, fb->len);
    if (rc != 0)
    {
        Serial.println("[cam] échec encodage base64");
        return "";
    }
    return String(reinterpret_cast<char *>(buf.data()), written);
}

/** Capture une photo et la renvoie encodée en base64 ; chaîne vide si la caméra est indisponible/échoue. */
static String capturePhotoBase64()
{
    if (!g_camera_ready)
        return "";

    MutexGuard guard(g_cameraMutex);
    camera_fb_t *fb = esp_camera_fb_get();
    if (!fb)
    {
        Serial.println("[cam] échec capture");
        return "";
    }
    String encoded = encodeFbToBase64(fb);
    esp_camera_fb_return(fb);
    return encoded;
}

// ---------------------------------------------------------------------------
// Persistance de la file (carte SD, une ligne JSON par paquet en attente)
// ---------------------------------------------------------------------------

// La synchro tourne dans sa propre tâche FreeRTOS (voir setup()/syncTask) pour
// lui donner une pile dédiée assez grande pour la poignée de main TLS de
// mbedTLS — elle peut donc s'exécuter en parallèle de handleScan() (tâche
// loop()/webserver), qui touche le même fichier sur la carte SD.
static SemaphoreHandle_t g_sdMutex = nullptr;

static size_t queueLength()
{
    MutexGuard guard(g_sdMutex);
    if (!SD_MMC.exists(QUEUE_FILE))
        return 0;
    File f = SD_MMC.open(QUEUE_FILE, "r");
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
    MutexGuard guard(g_sdMutex);
    File f = SD_MMC.open(QUEUE_FILE, "a");
    if (!f)
    {
        Serial.println("[queue] échec ouverture carte SD en écriture");
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
    MutexGuard guard(g_sdMutex);
    if (!SD_MMC.exists(QUEUE_FILE))
        return true;

    File f = SD_MMC.open(QUEUE_FILE, "r");
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
    MutexGuard guard(g_sdMutex);
    if (!SD_MMC.exists(QUEUE_FILE))
        return;

    File in = SD_MMC.open(QUEUE_FILE, "r");
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

    File out = SD_MMC.open(QUEUE_FILE, "w");
    if (!out)
        return;
    out.print(kept);
    out.close();
}

/**
 * Injecte après-coup une photo dans un paquet déjà en file (§4.1) — voir
 * processScan(), qui répond au téléphone avant de capturer. No-op silencieux
 * si le paquet a déjà été synchronisé/retiré entre-temps.
 */
static void attachPhotoToQueue(const String &localId, const String &photoBase64)
{
    MutexGuard guard(g_sdMutex);
    if (!SD_MMC.exists(QUEUE_FILE))
        return;

    File in = SD_MMC.open(QUEUE_FILE, "r");
    if (!in)
        return;

    String rebuilt;
    bool found = false;
    while (in.available())
    {
        String line = in.readStringUntil('\n');
        String trimmed = line;
        trimmed.trim();
        if (trimmed.length() == 0)
            continue;

        if (!found)
        {
            DynamicJsonDocument doc(PACKET_JSON_CAPACITY);
            if (deserializeJson(doc, trimmed) == DeserializationError::Ok && doc["local_id"].as<String>() == localId)
            {
                doc["payload"]["photo_base64"] = photoBase64;
                String updated;
                serializeJson(doc, updated);
                rebuilt += updated;
                rebuilt += '\n';
                found = true;
                continue;
            }
        }
        rebuilt += trimmed;
        rebuilt += '\n';
    }
    in.close();

    if (!found)
        return;

    File out = SD_MMC.open(QUEUE_FILE, "w");
    if (!out)
        return;
    out.print(rebuilt);
    out.close();
}

// ---------------------------------------------------------------------------
// Moteur de pull périodique vers l'API
// ---------------------------------------------------------------------------

static void syncWithApi()
{
    if (WiFi.status() != WL_CONNECTED)
        return;

    MutexGuard apiGuard(g_apiMutex);

    std::vector<QueuedPacket> batch;
    if (!readQueueBatch(batch) || batch.empty())
        return;

    DynamicJsonDocument body(SYNC_BODY_JSON_CAPACITY);
    JsonArray packets = body.createNestedArray("packets");
    for (const auto &p : batch)
    {
        DynamicJsonDocument item(PACKET_JSON_CAPACITY);
        if (deserializeJson(item, p.raw_json) != DeserializationError::Ok)
            continue;
        packets.add(item.as<JsonObject>());
    }

    String payload;
    serializeJson(body, payload);

    WiFiClientSecure client;
    client.setInsecure();
    client.setHandshakeTimeout(20);
    HTTPClient http;
    String url = String(API_BASE_URL) + API_RELAY_SYNC_PATH;
    http.begin(client, url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.setTimeout(20000); // paquets plus lourds avec la photo : marge sur le timeout

    int status = http.POST(payload);
    if (status != 200)
    {
        Serial.printf("[sync] échec HTTP %d (%s), url=%s, ip=%s, passerelle=%s\n",
                      status,
                      http.errorToString(status).c_str(),
                      url.c_str(),
                      WiFi.localIP().toString().c_str(),
                      WiFi.gatewayIP().toString().c_str());
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
 * débordement de pile ("Guru Meditation Error: Double exception" pendant
 * ecp_drbg_seed, ~15s après le boot, à chaque premier cycle de synchro).
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
// Reconnaissance faciale embarquée (ESP-WHO) — pointage 100% facial en
// principal, le flux BLE/QR ci-dessous reste actif en secours. Voir
// hardware/README.md § "Reconnaissance faciale embarquée".
// ---------------------------------------------------------------------------

static SemaphoreHandle_t g_faceCacheMutex = nullptr;
static std::vector<EnrolledFace> g_faceCache;

// Anti-doublon : horodatage (millis()) du dernier pointage facial accepté par
// enseignant. Petite liste linéaire : le nombre d'enseignants enrôlés reste
// modeste (quelques dizaines à centaines), pas besoin d'une structure plus
// élaborée.
struct LastCheckin
{
    uint32_t enseignant_id;
    uint32_t at_millis;
};
static std::vector<LastCheckin> g_lastCheckins;

static bool recentlyCheckedIn(uint32_t enseignantId)
{
    for (const auto &c : g_lastCheckins)
    {
        if (c.enseignant_id == enseignantId)
        {
            return (millis() - c.at_millis) < RECOGNITION_DEBOUNCE_MS;
        }
    }
    return false;
}

static void markCheckedIn(uint32_t enseignantId)
{
    for (auto &c : g_lastCheckins)
    {
        if (c.enseignant_id == enseignantId)
        {
            c.at_millis = millis();
            return;
        }
    }
    g_lastCheckins.push_back({enseignantId, millis()});
}

static void initBuzzer()
{
    pinMode(BUZZER_GPIO, OUTPUT);
    digitalWrite(BUZZER_GPIO, LOW);
}

static void beep(uint32_t durationMs = 150)
{
    digitalWrite(BUZZER_GPIO, HIGH);
    vTaskDelay(pdMS_TO_TICKS(durationMs));
    digitalWrite(BUZZER_GPIO, LOW);
}

/** Met en file un paquet `facial_scan` — même file/moteur de synchro que le flux BLE/QR (voir appendToQueue/syncWithApi). */
static void enqueueFacialScan(uint32_t enseignantId, float score, const String &photoBase64)
{
    if (queueLength() >= MAX_QUEUE_SIZE)
    {
        Serial.println("[face] file locale saturée, pointage facial abandonné");
        return;
    }

    String capturedAt = currentIsoTimestamp();
    if (capturedAt.length() == 0)
        return; // pas encore synchronisé en heure

    char localId[40];
    makeLocalId(localId, sizeof(localId));

    DynamicJsonDocument out(PACKET_JSON_CAPACITY);
    out["local_id"] = localId;
    out["type"] = "facial_scan";
    out["captured_at"] = capturedAt;
    JsonObject payload = out.createNestedObject("payload");
    payload["enseignant_id"] = enseignantId;
    payload["score_confiance"] = score;
    if (photoBase64.length() > 0)
        payload["photo_base64"] = photoBase64;

    String serialized;
    serializeJson(out, serialized);
    appendToQueue(serialized);
}

/** Capture une frame, tente une reconnaissance, bipe + pointe si un enseignant enrôlé est identifié. */
static void recognitionTick()
{
    if (!g_camera_ready || !shouldRunRecognition())
        return;

    camera_fb_t *fb = nullptr;
    {
        MutexGuard guard(g_cameraMutex);
        fb = esp_camera_fb_get();
    }
    if (!fb)
        return;

    FaceEmbedding probe;
    bool detected = faceEngineExtractEmbedding(fb, probe);

    if (!detected)
    {
        MutexGuard guard(g_cameraMutex);
        esp_camera_fb_return(fb);
        return;
    }

    bool found = false;
    EnrolledFace matchCopy;
    float score = -1.0f;
    {
        MutexGuard guard(g_faceCacheMutex);
        const EnrolledFace *match = faceEngineFindBestMatch(g_faceCache, probe, score);
        // Copie l'entrée trouvée avant de relâcher le mutex : `match`
        // référence un élément de g_faceCache, pas sûr de rester valide après
        // un upsert concurrent côté syncFaceManifestTask.
        if (match)
        {
            matchCopy = *match;
            found = true;
        }
    }

    if (found && !recentlyCheckedIn(matchCopy.enseignant_id))
    {
        String photoBase64 = encodeFbToBase64(fb);
        {
            MutexGuard guard(g_cameraMutex);
            esp_camera_fb_return(fb);
        }
        markCheckedIn(matchCopy.enseignant_id);
        enqueueFacialScan(matchCopy.enseignant_id, score, photoBase64);
        Serial.printf("[face] reconnu enseignant_id=%u score=%.2f\n", (unsigned)matchCopy.enseignant_id, score);
        return;
    }

    MutexGuard guard(g_cameraMutex);
    esp_camera_fb_return(fb);
}

static void recognitionTask(void *)
{
    bool lastRawState = hw201IsHigh();
    bool stableState = lastRawState;
    uint32_t stateChangedAt = millis();

    // Si HW201 est déjà haut au démarrage, il constitue un seul événement.
    g_hw201Armed = true;

    for (;;)
    {
        vTaskDelay(pdMS_TO_TICKS(HW201_POLL_INTERVAL_MS));
        bool rawState = hw201IsHigh();
        uint32_t now = millis();

        if (rawState != lastRawState)
        {
            lastRawState = rawState;
            stateChangedAt = now;
        }

        if (rawState != stableState && now - stateChangedAt >= HW201_DEBOUNCE_MS)
        {
            stableState = rawState;
            if (!stableState)
            {
                // Seul un LOW stable réarme le prochain front montant.
                g_hw201Armed = true;
            }
        }

        if (stableState && g_hw201Armed && !g_qrScanInProgress)
        {
            // Consomme le front montant avant la capture : un niveau HIGH
            // maintenu, ou des rebonds, ne relancent pas la reconnaissance.
            g_hw201Armed = false;
            Serial.println("[hw201] HIGH détecté, reconnaissance faciale déclenchée");
            beep();
            recognitionTick();
        }
    }
}

/** Télécharge une photo (URL renvoyée par le manifest) dans un buffer PSRAM. */
static bool downloadPhoto(const String &url, std::vector<uint8_t> &out)
{
    MutexGuard apiGuard(g_apiMutex);
    for (uint8_t attempt = 1; attempt <= 3; ++attempt)
    {
        WiFiClientSecure client;
        client.setInsecure();
        client.setHandshakeTimeout(20);
        HTTPClient http;
        http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
        http.setReuse(false);
        http.useHTTP10(true);
        http.begin(client, url);
        http.setTimeout(20000);
        int status = http.GET();
        if (status != 200)
        {
            Serial.printf("[face-sync] échec photo tentative %u/3 HTTP %d\n", (unsigned)attempt, status);
            http.end();
            vTaskDelay(pdMS_TO_TICKS(250));
            continue;
        }

        int len = http.getSize();
        WiFiClient *stream = http.getStreamPtr();
        out.resize(len > 0 ? len : 0);
        size_t got = 0;
        while (http.connected() && (int)got < len)
        {
            size_t avail = stream->available();
            if (avail == 0)
            {
                vTaskDelay(pdMS_TO_TICKS(10));
                continue;
            }
            size_t toRead = std::min(avail, (size_t)(len - got));
            got += stream->readBytes(out.data() + got, toRead);
        }
        http.end();

        if (got == (size_t)len && len > 0)
            return true;

        Serial.printf("[face-sync] photo incomplète tentative %u/3 (%u/%d octets)\n", (unsigned)attempt, (unsigned)got, len);
        if (attempt < 3)
        {
            vTaskDelay(pdMS_TO_TICKS(250));
        }
    }

    Serial.println("[face-sync] abandon téléchargement photo après 3 tentatives");
    return false;
}

/** Enrôle un enseignant depuis sa photo de référence : embedding calculé localement, puis transmis à l'API. */
static void enrollFromPhoto(uint32_t enseignantId, const String &nom, const String &photoUrl)
{
    std::vector<uint8_t> jpeg;
    if (!downloadPhoto(photoUrl, jpeg))
        return;

    FaceEmbedding embedding;
    if (!faceEngineExtractEmbeddingFromJpeg(jpeg.data(), jpeg.size(), embedding))
    {
        Serial.printf("[face-sync] aucun visage détecté sur la photo de l'enseignant %u\n", (unsigned)enseignantId);
        return;
    }

    DynamicJsonDocument body(8192);
    body["enseignant_id"] = enseignantId;
    JsonArray emb = body.createNestedArray("embedding");
    for (float v : embedding)
        emb.add(v);
    String payload;
    serializeJson(body, payload);

    MutexGuard apiGuard(g_apiMutex);
    WiFiClientSecure client;
    client.setInsecure();
    HTTPClient http;
    http.begin(client, String(API_BASE_URL) + API_VISAGES_ENROLL_PATH);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + KIOSK_API_TOKEN);
    int status = http.POST(payload);
    http.end();

    if (status != 201)
    {
        Serial.printf("[face-sync] échec enrôlement API HTTP %d (enseignant %u)\n", status, (unsigned)enseignantId);
        return;
    }

    EnrolledFace face;
    face.enseignant_id = enseignantId;
    face.nom = nom;
    face.embedding = embedding;
    {
        MutexGuard guard(g_faceCacheMutex);
        faceCacheUpsert(g_faceCache, face);
        faceCacheSave(g_faceCache);
    }
    Serial.printf("[face-sync] enseignant %u enrôlé localement\n", (unsigned)enseignantId);
}

/** Récupère le manifest (photos à enrôler + embeddings déjà partagés par d'autres bornes) et met à jour le cache local. */
static void syncFaceManifest()
{
    if (WiFi.status() != WL_CONNECTED)
        return;

    String body;
    {
        MutexGuard apiGuard(g_apiMutex);
        WiFiClientSecure client;
        client.setInsecure();
        client.setHandshakeTimeout(20);
        HTTPClient http;
        http.begin(client, String(API_BASE_URL) + API_KIOSK_MANIFEST_PATH);
        http.addHeader("Authorization", String("Bearer ") + KIOSK_API_TOKEN);
        http.setTimeout(15000);
        int status = http.GET();
        if (status != 200)
        {
            Serial.printf("[face-sync] échec manifest HTTP %d (%s), url=%s, ip=%s, passerelle=%s\n",
                          status,
                          http.errorToString(status).c_str(),
                          (String(API_BASE_URL) + API_KIOSK_MANIFEST_PATH).c_str(),
                          WiFi.localIP().toString().c_str(),
                          WiFi.gatewayIP().toString().c_str());
            http.end();
            return;
        }
        body = http.getString();
        http.end();
    }

    DynamicJsonDocument doc(65536);
    DeserializationError manifestError = deserializeJson(doc, body);
    if (manifestError != DeserializationError::Ok)
    {
        Serial.printf("[face-sync] manifest illisible (%s, %u octets)\n", manifestError.c_str(), (unsigned)body.length());
        return;
    }

    for (JsonObject e : doc["enseignants"].as<JsonArray>())
    {
        uint32_t id = e["id"] | 0;
        String nom = e["nom"] | "";
        String photoUrl = e["photo_url"] | "";
        if (id == 0 || photoUrl.length() == 0)
            continue;
        enrollFromPhoto(id, nom, photoUrl);
    }

    for (JsonObject emb : doc["embeddings"].as<JsonArray>())
    {
        uint32_t id = emb["enseignant_id"] | 0;
        if (id == 0)
            continue;

        EnrolledFace face;
        face.enseignant_id = id;
        JsonArray arr = emb["embedding"].as<JsonArray>();
        face.embedding.reserve(arr.size());
        for (JsonVariant v : arr)
            face.embedding.push_back(v.as<float>());
        if (face.embedding.empty())
            continue;

        MutexGuard guard(g_faceCacheMutex);
        faceCacheUpsert(g_faceCache, face);
    }

    {
        MutexGuard guard(g_faceCacheMutex);
        faceCacheSave(g_faceCache);
    }
    Serial.printf("[face-sync] cache local : %u visage(s) enrôlé(s)\n", (unsigned)g_faceCache.size());
}

static void syncFaceManifestTask(void *)
{
    for (;;)
    {
        vTaskDelay(pdMS_TO_TICKS(FACE_MANIFEST_SYNC_INTERVAL_MS));
        syncFaceManifest();
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
 * proximité, même rôle que l'ancien BSSID WiFi côté API) + une photo caméra
 * (payload.photo_base64), écrit sur flash, puis répond — l'envoi vers l'API
 * est différé au prochain cycle de `syncWithApi()`.
 */
static String processScan(const String &rawJson)
{
    g_qrScanInProgress = true;

    if (queueLength() >= MAX_QUEUE_SIZE)
    {
        g_qrScanInProgress = false;
        return "{\"error\":\"file locale saturée, réessayez plus tard\"}";
    }

    DynamicJsonDocument in(4096);
    if (deserializeJson(in, rawJson) != DeserializationError::Ok)
    {
        g_qrScanInProgress = false;
        return "{\"error\":\"JSON invalide\"}";
    }

    const char *type = in["type"] | "";
    if (strcmp(type, "scan") != 0 && strcmp(type, "admin_proxy") != 0)
    {
        g_qrScanInProgress = false;
        return "{\"error\":\"type invalide\"}";
    }
    if (!in.containsKey("teacher_token") || !in.containsKey("payload"))
    {
        g_qrScanInProgress = false;
        return "{\"error\":\"teacher_token et payload requis\"}";
    }

    String capturedAt;
    if (in.containsKey("captured_at"))
    {
        capturedAt = in["captured_at"].as<String>();
    }
    else
    {
        // Cet ESP32 a son propre accès au modem : il fait son propre NTP, pas
        // besoin de synchro horaire par un second module.
        capturedAt = currentIsoTimestamp();
        if (capturedAt.length() == 0)
        {
            g_qrScanInProgress = false;
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
        out["payload"]["bssid"] = BLEDevice::getAddress().toString();
    }
    out["captured_at"] = capturedAt;

    String serialized;
    serializeJson(out, serialized);

    // Écriture sur flash AVANT toute réponse au téléphone ou tentative
    // réseau : c'est ce qui garantit qu'un scan accepté ne se perd jamais,
    // même si l'ESP32 redémarre dans la seconde qui suit. La photo n'est PAS
    // encore dedans à ce stade (voir plus bas) : sans DS3231, on ne fait déjà
    // plus attendre le téléphone pour l'heure, inutile de le refaire attendre
    // pour la caméra.
    appendToQueue(serialized);

    StaticJsonDocument<160> resp;
    resp["queued"] = true;
    resp["local_id"] = localId;
    resp["photo_captured"] = false; // capture tentée juste après, voir attachPhotoToQueue()
    String respStr;
    serializeJson(resp, respStr);

    // Répond tout de suite (§4.1) : le téléphone n'a plus besoin d'attendre
    // l'exposition caméra + l'encodage JPEG/base64 (plusieurs centaines de ms)
    // pour fermer son overlay de transmission — seule l'écriture SD ci-dessus,
    // déjà faite, conditionnait la durabilité du scan.
    if (g_bleResultChar)
    {
        g_bleResultChar->setValue(respStr);
        g_bleResultChar->notify();
    }

    // Capturée au plus près possible de la réception du scan malgré la
    // réponse déjà partie : c'est la preuve que *cette* personne était
    // physiquement devant la borne à cet instant. Attachée après-coup à
    // l'entrée déjà en file — si celle-ci a déjà été synchronisée et retirée
    // entre-temps (fenêtre de course avec syncTask), le pointage reste valide,
    // simplement sans preuve photo pour ce scan.
    String photoBase64 = capturePhotoBase64();
    if (photoBase64.length() == 0)
    {
        Serial.println("[cam] photo indisponible pour ce scan, paquet envoyé sans preuve visuelle");
    }
    else
    {
        attachPhotoToQueue(localId, photoBase64);
    }

    g_qrScanInProgress = false;

    return respStr;
}

// ---------------------------------------------------------------------------
// Serveur BLE — reçoit le pointage du téléphone de l'enseignant (transport principal)
// ---------------------------------------------------------------------------

// processScan() écrit sur la carte SD et capture une photo : trop lent
// pour tourner dans le callback GATT lui-même, qui s'exécute sur la tâche
// hôte BLE. L'y bloquer perturbe le timing de la connexion BLE (le
// contrôleur ne peut plus servir les événements de connexion à temps) et fait
// tomber la liaison (LINK_SUPERVISION_TIMEOUT), avec une erreur GATT_ERROR
// (133) côté téléphone sur l'écriture en cours — de façon systématique, pas
// aléatoire. onWrite() se contente donc de recopier la requête et de poser un
// drapeau ; le traitement réel a lieu dans loop(), sur la tâche principale —
// processScan() y notifie déjà le résultat dès le paquet écrit sur SD, avant
// même de capturer la photo (voir plus bas), pour ne pas faire attendre le
// téléphone le temps de l'exposition caméra + encodage JPEG/base64.
static SemaphoreHandle_t g_pendingScanMutex = nullptr;
static String g_pendingScanJson;
static volatile bool g_pendingScan = false;

class ScanCharCallbacks : public BLECharacteristicCallbacks
{
    void onWrite(BLECharacteristic *characteristic) override
    {
        MutexGuard guard(g_pendingScanMutex);
        g_pendingScanJson = String(characteristic->getValue().c_str());
        g_pendingScan = true;
    }
};

/** Appelé depuis loop() : traite la requête en attente (s'il y en a une) hors du callback GATT. */
static void processPendingBleScan()
{
    if (!g_pendingScan)
        return;

    String rawJson;
    {
        MutexGuard guard(g_pendingScanMutex);
        rawJson = g_pendingScanJson;
        g_pendingScan = false;
    }

    g_qrScanInProgress = true;

    // processScan() a déjà notifié le résultat en BLE avant la capture photo
    // (voir plus haut) ; sa valeur de retour n'est utile qu'au débogage HTTP.
    processScan(rawJson);
    g_qrScanInProgress = false;
}

static void setupBle()
{
    BLEDevice::init(BLE_DEVICE_NAME);

    BLEServer *bleServer = BLEDevice::createServer();
    BLEService *service = bleServer->createService(BLE_SERVICE_UUID);

    BLECharacteristic *scanChar = service->createCharacteristic(
        BLE_CHAR_SCAN_UUID, BLECharacteristic::PROPERTY_WRITE);
    scanChar->setCallbacks(new ScanCharCallbacks());

    g_bleResultChar = service->createCharacteristic(
        BLE_CHAR_RESULT_UUID, BLECharacteristic::PROPERTY_READ | BLECharacteristic::PROPERTY_NOTIFY);

    service->start();

    BLEAdvertising *advertising = BLEDevice::getAdvertising();
    advertising->addServiceUUID(BLE_SERVICE_UUID);
    advertising->start();

    Serial.print("[ble] serveur démarré, adresse=");
    Serial.println(BLEDevice::getAddress().toString().c_str());
}

// ---------------------------------------------------------------------------
// Serveur HTTP local — legacy/débogage uniquement (voir processScan ci-dessus)
// ---------------------------------------------------------------------------

static void handleScan()
{
    g_qrScanInProgress = true;
    if (!server.hasArg("plain"))
    {
        server.send(400, "application/json", "{\"error\":\"corps JSON manquant\"}");
        g_qrScanInProgress = false;
        return;
    }
    String resp = processScan(server.arg("plain"));
    bool queued = resp.indexOf("\"queued\"") >= 0;
    server.send(queued ? 202 : 400, "application/json", resp);
    g_qrScanInProgress = false;
}

static void handleNotFound()
{
    server.send(404, "application/json", "{\"error\":\"not found\"}");
}

// ---------------------------------------------------------------------------

static void connectWifiIfNeeded()
{
    wl_status_t status = WiFi.status();
    // WL_IDLE_STATUS signifie qu'une tentative est encore en cours. Un
    // nouvel appel à WiFi.begin() dans cet état provoque ESP_ERR_WIFI_STATE
    // et interrompt l'authentification en cours.
    if (status != WL_DISCONNECTED && status != WL_CONNECT_FAILED &&
        status != WL_CONNECTION_LOST && status != WL_NO_SSID_AVAIL)
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
    Serial.begin(115200);
    Serial.printf("[mem] PSRAM détectée: %s\n", psramFound() ? "oui" : "non");
    Serial.printf("[mem] PSRAM totale: %lu octets\n", (unsigned long)ESP.getPsramSize());
    Serial.printf("[mem] PSRAM libre: %lu octets\n", (unsigned long)ESP.getFreePsram());
    Serial.printf("[mem] heap interne libre: %lu octets\n", (unsigned long)ESP.getFreeHeap());

    pinMode(HW201_GPIO, INPUT_PULLDOWN);

    SD_MMC.setPins(SD_MMC_CLK_GPIO, SD_MMC_CMD_GPIO, SD_MMC_D0_GPIO);
    if (!SD_MMC.begin("/sdcard", true))
    { // true = mode 1 bit (3 IOs)
        Serial.println("[fs] échec montage carte SD");
    }
    Serial.printf("[queue] %u paquet(s) en attente au démarrage\n", (unsigned)queueLength());

    g_camera_ready = initCamera();
    Serial.println(g_camera_ready ? "[cam] caméra initialisée" : "[cam] caméra indisponible — les scans continueront sans photo");

    // WiFi en client uniquement désormais : le téléphone parle en BLE, plus
    // besoin du rôle AP (voir setupBle() ci-dessous).
    WiFi.mode(WIFI_STA);
    WiFi.begin(STA_SSID, STA_PASSWORD);

    setupBle();

    // Conservé pour du débogage (curl direct sur l'IP STA) — le téléphone
    // n'utilise plus ce chemin, voir processScan()/setupBle().
    server.on("/scan", HTTP_POST, handleScan);
    server.onNotFound(handleNotFound);
    server.begin();
    Serial.println("[http] serveur local démarré sur le port 80 (débogage)");

    g_sdMutex = xSemaphoreCreateMutex();
    g_apiMutex = xSemaphoreCreateMutex();
    g_pendingScanMutex = xSemaphoreCreateMutex();
    g_cameraMutex = xSemaphoreCreateMutex();
    g_faceCacheMutex = xSemaphoreCreateMutex();
    // Cœur 1 (APP_CPU), comme loopTask par défaut sur Arduino-ESP32 — la pile
    // dédiée de 16 Ko est la partie qui compte ici, pas l'affinité de cœur.
    xTaskCreatePinnedToCore(syncTask, "sync_task", 16384, nullptr, 1, nullptr, 1);

    // Reconnaissance faciale embarquée (ESP-WHO) — voir hardware/README.md.
    // BLE/QR (ci-dessus) reste actif en parallèle comme secours.
    initBuzzer();
    if (faceEngineInit())
    {
        faceCacheLoad(g_faceCache);
        Serial.printf("[face] cache local chargé : %u visage(s)\n", (unsigned)g_faceCache.size());
        xTaskCreatePinnedToCore(recognitionTask, "recognition_task", 8192, nullptr, 1, nullptr, 1);
        xTaskCreatePinnedToCore(syncFaceManifestTask, "face_sync_task", 16384, nullptr, 1, nullptr, 1);
    }
    else
    {
        Serial.println("[face] moteur de reconnaissance indisponible — pointage facial désactivé, BLE/QR reste seul actif");
    }
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
    // fait rater l'unique ligne imprimée au démarrage — inutile de redémarrer
    // la borne en boucle pour l'attraper, un intervalle court garantit de
    // l'attraper même si la borne replante peu après le boot.
    static uint32_t lastBleAddrPrint = 0;
    static bool bleAddrPrintedOnce = false;
    if (!bleAddrPrintedOnce || millis() - lastBleAddrPrint > 3000)
    {
        bleAddrPrintedOnce = true;
        lastBleAddrPrint = millis();
        Serial.print("[ble] adresse=");
        Serial.println(BLEDevice::getAddress().toString().c_str());
        Serial.flush();
    }
}
extern "C" void app_main()
{
    initArduino(); // initialise le runtime Arduino (HAL, millis(), Serial, etc.) au sein d'ESP-IDF
    setup();
    for (;;)
    {
        loop();
        vTaskDelay(pdMS_TO_TICKS(1)); // laisse respirer le watchdog de tâche (actif par défaut sous IDF pur, contrairement au framework Arduino)
    }
}