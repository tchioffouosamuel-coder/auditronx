/**
 * ESP32 "borne" (module unique) — point d'accès WiFi local pour le téléphone
 * de l'enseignant ET client WiFi connecté au modem, simultanément
 * (WIFI_AP_STA). Colocalisé avec le modem : plus de second module ESP32,
 * plus de saut ESP-NOW intermédiaire.
 *
 * Reçoit le pointage en HTTP local, l'écrit immédiatement sur flash
 * (LittleFS) avant toute tentative réseau, puis un moteur de pull
 * périodique le pousse vers l'API dès qu'internet est disponible. Un
 * paquet n'est retiré de la file locale que sur confirmation explicite de
 * l'API (`ok` ou `rejected`) — jamais avant, pour ne rien perdre en cas de
 * coupure secteur ou réseau.
 */
#include <Arduino.h>
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <LittleFS.h>
#include <ArduinoJson.h>
#include <time.h>
#include <vector>

#include "config.h"

static WebServer server(80);
static uint32_t g_local_id_counter = 0;
static bool g_time_ready = false;

static void makeLocalId(char *out, size_t outLen) {
    snprintf(out, outLen, "borne-%lu-%lu", (unsigned long) millis(), (unsigned long) (++g_local_id_counter));
}

// ---------------------------------------------------------------------------
// Persistance de la file (LittleFS, une ligne JSON par paquet en attente)
// ---------------------------------------------------------------------------

static size_t queueLength() {
    if (!LittleFS.exists(QUEUE_FILE)) return 0;
    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) return 0;
    size_t n = 0;
    while (f.available()) {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() > 0) n++;
    }
    f.close();
    return n;
}

static void appendToQueue(const String &json) {
    File f = LittleFS.open(QUEUE_FILE, "a");
    if (!f) {
        Serial.println("[queue] échec ouverture LittleFS en écriture");
        return;
    }
    f.println(json);
    f.close();
}

struct QueuedPacket {
    String local_id;
    String raw_json;
};

static bool readQueueBatch(std::vector<QueuedPacket> &out) {
    if (!LittleFS.exists(QUEUE_FILE)) return true;

    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) return false;

    while (f.available() && out.size() < SYNC_BATCH_SIZE) {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() == 0) continue;

        StaticJsonDocument<1024> doc;
        if (deserializeJson(doc, line) != DeserializationError::Ok) continue;

        QueuedPacket p;
        p.local_id = doc["local_id"].as<String>();
        p.raw_json = line;
        out.push_back(p);
    }
    f.close();
    return true;
}

/** Réécrit la file sans les local_id passés en paramètre (terminaux : ok ou rejected). */
static void removeFromQueue(const std::vector<String> &idsToRemove) {
    if (idsToRemove.empty()) return;
    if (!LittleFS.exists(QUEUE_FILE)) return;

    File in = LittleFS.open(QUEUE_FILE, "r");
    if (!in) return;

    String kept;
    while (in.available()) {
        String line = in.readStringUntil('\n');
        String trimmed = line;
        trimmed.trim();
        if (trimmed.length() == 0) continue;

        StaticJsonDocument<1024> doc;
        if (deserializeJson(doc, trimmed) != DeserializationError::Ok) {
            kept += trimmed;
            kept += '\n';
            continue;
        }

        String id = doc["local_id"].as<String>();
        bool remove = false;
        for (const auto &rid : idsToRemove) {
            if (rid == id) { remove = true; break; }
        }
        if (!remove) {
            kept += trimmed;
            kept += '\n';
        }
    }
    in.close();

    File out = LittleFS.open(QUEUE_FILE, "w");
    if (!out) return;
    out.print(kept);
    out.close();
}

// ---------------------------------------------------------------------------
// Moteur de pull périodique vers l'API
// ---------------------------------------------------------------------------

static void syncWithApi() {
    if (WiFi.status() != WL_CONNECTED) return;

    std::vector<QueuedPacket> batch;
    if (!readQueueBatch(batch) || batch.empty()) return;

    StaticJsonDocument<4096> body;
    JsonArray packets = body.createNestedArray("packets");
    for (const auto &p : batch) {
        StaticJsonDocument<1024> item;
        if (deserializeJson(item, p.raw_json) != DeserializationError::Ok) continue;
        packets.add(item.as<JsonObject>());
    }

    String payload;
    serializeJson(body, payload);

    HTTPClient http;
    String url = String(API_BASE_URL) + API_RELAY_SYNC_PATH;
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + RELAY_API_TOKEN);
    http.setTimeout(10000);

    int status = http.POST(payload);
    if (status != 200) {
        Serial.printf("[sync] échec HTTP %d, on retentera au prochain cycle\n", status);
        http.end();
        return; // rien n'est retiré de la file : nouvelle tentative plus tard
    }

    String respBody = http.getString();
    http.end();

    StaticJsonDocument<4096> resp;
    if (deserializeJson(resp, respBody) != DeserializationError::Ok) {
        Serial.println("[sync] réponse API illisible, on retentera au prochain cycle");
        return;
    }

    std::vector<String> toRemove;
    for (JsonObject result : resp["results"].as<JsonArray>()) {
        const char *status_ = result["status"] | "";
        const char *localId = result["local_id"] | "";
        // "ok" (accepté) et "rejected" (invalide, inutile de réessayer) sont
        // terminaux : on purge. "retry" reste en file pour le prochain cycle.
        if (strcmp(status_, "ok") == 0 || strcmp(status_, "rejected") == 0) {
            toRemove.push_back(String(localId));
        }
    }

    removeFromQueue(toRemove);
    Serial.printf("[sync] %u paquet(s) envoyés, %u confirmé(s)/rejeté(s)\n", (unsigned) batch.size(), (unsigned) toRemove.size());
}

// ---------------------------------------------------------------------------
// Serveur HTTP local — reçoit le pointage du téléphone de l'enseignant
// ---------------------------------------------------------------------------

/**
 * POST /scan  { "type": "scan"|"admin_proxy", "teacher_token": "...", "payload": {...}, "captured_at"?: "ISO8601" }
 *
 * Le téléphone parle à la borne exactement comme il parlerait à l'API en
 * ligne (mêmes champs `payload` : qr_code/bssid[/enseignant_id/motif]). La
 * borne ajoute local_id + captured_at, écrit sur flash, puis répond —
 * l'envoi vers l'API est différé au prochain cycle de `syncWithApi()`.
 */
static void handleScan() {
    if (!server.hasArg("plain")) {
        server.send(400, "application/json", "{\"error\":\"corps JSON manquant\"}");
        return;
    }

    if (queueLength() >= MAX_QUEUE_SIZE) {
        server.send(503, "application/json", "{\"error\":\"file locale saturée, réessayez plus tard\"}");
        return;
    }

    StaticJsonDocument<1024> in;
    if (deserializeJson(in, server.arg("plain")) != DeserializationError::Ok) {
        server.send(400, "application/json", "{\"error\":\"JSON invalide\"}");
        return;
    }

    const char *type = in["type"] | "";
    if (strcmp(type, "scan") != 0 && strcmp(type, "admin_proxy") != 0) {
        server.send(400, "application/json", "{\"error\":\"type invalide\"}");
        return;
    }
    if (!in.containsKey("teacher_token") || !in.containsKey("payload")) {
        server.send(400, "application/json", "{\"error\":\"teacher_token et payload requis\"}");
        return;
    }

    String capturedAt;
    if (in.containsKey("captured_at")) {
        capturedAt = in["captured_at"].as<String>();
    } else if (g_time_ready) {
        // Contrairement à l'ancienne borne déportée (pas d'internet propre,
        // horloge reçue par relais), cet ESP32 a son propre accès au modem :
        // il fait son propre NTP, pas besoin de synchro horaire inter-module.
        time_t now;
        time(&now);
        struct tm tmVal;
        gmtime_r(&now, &tmVal);
        char buf[25];
        strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%SZ", &tmVal);
        capturedAt = String(buf);
    } else {
        server.send(503, "application/json", "{\"error\":\"borne non synchronisée en heure, réessayez dans un instant\"}");
        return;
    }

    char localId[40];
    makeLocalId(localId, sizeof(localId));

    StaticJsonDocument<1024> out;
    out["local_id"] = localId;
    out["type"] = type;
    out["teacher_token"] = in["teacher_token"];
    out["payload"] = in["payload"];
    out["captured_at"] = capturedAt;

    String serialized;
    serializeJson(out, serialized);

    // Écriture sur flash AVANT toute réponse au téléphone ou tentative
    // réseau : c'est ce qui garantit qu'un scan accepté (202) ne se perd
    // jamais, même si l'ESP32 redémarre dans la seconde qui suit.
    appendToQueue(serialized);

    StaticJsonDocument<128> resp;
    resp["queued"] = true;
    resp["local_id"] = localId;
    String respStr;
    serializeJson(resp, respStr);
    server.send(202, "application/json", respStr);
}

static void handleNotFound() {
    server.send(404, "application/json", "{\"error\":\"not found\"}");
}

// ---------------------------------------------------------------------------

static void connectWifiIfNeeded() {
    if (WiFi.status() == WL_CONNECTED) return;

    static uint32_t lastAttempt = 0;
    if (millis() - lastAttempt < 10000) return;
    lastAttempt = millis();

    Serial.println("[wifi] tentative de connexion au modem...");
    WiFi.begin(STA_SSID, STA_PASSWORD);
}

static void onWifiConnected() {
    Serial.print("[wifi] connecté au modem, IP=");
    Serial.println(WiFi.localIP());

    configTime(0, 0, NTP_SERVER);
    struct tm timeinfo;
    if (getLocalTime(&timeinfo, 5000)) {
        g_time_ready = true;
        Serial.println("[time] NTP synchronisé");
    }
}

void setup() {
    Serial.begin(115200);

    if (!LittleFS.begin(true)) {
        Serial.println("[fs] échec montage LittleFS");
    }
    Serial.printf("[queue] %u paquet(s) en attente au démarrage\n", (unsigned) queueLength());

    // Les deux rôles à la fois : AP pour le téléphone, STA pour le modem.
    WiFi.mode(WIFI_AP_STA);
    WiFi.softAP(AP_SSID, AP_PASSWORD);
    Serial.print("[ap] SSID=");
    Serial.print(AP_SSID);
    Serial.print(" IP=");
    Serial.println(WiFi.softAPIP());

    WiFi.begin(STA_SSID, STA_PASSWORD);

    server.on("/scan", HTTP_POST, handleScan);
    server.onNotFound(handleNotFound);
    server.begin();
    Serial.println("[http] serveur local démarré sur le port 80");
}

void loop() {
    server.handleClient();

    static bool wasConnected = false;
    bool isConnected = WiFi.status() == WL_CONNECTED;
    if (isConnected && !wasConnected) onWifiConnected();
    wasConnected = isConnected;

    if (!isConnected) connectWifiIfNeeded();

    static uint32_t lastSync = 0;
    if (millis() - lastSync > SYNC_INTERVAL_MS) {
        lastSync = millis();
        syncWithApi();
    }
}
