#include "face_cache.h"
#include "config.h"

#include <ArduinoJson.h>
#include <SD_MMC.h>

void faceCacheLoad(std::vector<EnrolledFace> &out) {
    out.clear();
    if (!SD_MMC.exists(FACE_CACHE_FILE)) return;

    File f = SD_MMC.open(FACE_CACHE_FILE, "r");
    if (!f) return;

    while (f.available()) {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() == 0) continue;

        DynamicJsonDocument doc(8192);
        if (deserializeJson(doc, line) != DeserializationError::Ok) continue;

        EnrolledFace face;
        face.enseignant_id = doc["enseignant_id"] | 0;
        face.nom = doc["nom"] | "";
        JsonArray emb = doc["embedding"].as<JsonArray>();
        face.embedding.reserve(emb.size());
        for (JsonVariant v : emb) face.embedding.push_back(v.as<float>());

        if (face.enseignant_id != 0 && !face.embedding.empty()) out.push_back(face);
    }
    f.close();
}

void faceCacheSave(const std::vector<EnrolledFace> &faces) {
    File f = SD_MMC.open(FACE_CACHE_FILE, "w");
    if (!f) {
        Serial.println("[face-cache] échec ouverture carte SD en écriture");
        return;
    }

    for (const auto &face : faces) {
        DynamicJsonDocument doc(8192);
        doc["enseignant_id"] = face.enseignant_id;
        doc["nom"] = face.nom;
        JsonArray emb = doc.createNestedArray("embedding");
        for (float v : face.embedding) emb.add(v);

        String line;
        serializeJson(doc, line);
        f.println(line);
    }
    f.close();
}

void faceCacheUpsert(std::vector<EnrolledFace> &faces, const EnrolledFace &face) {
    for (auto &existing : faces) {
        if (existing.enseignant_id == face.enseignant_id) {
            existing = face;
            return;
        }
    }
    faces.push_back(face);
}
