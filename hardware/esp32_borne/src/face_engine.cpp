/**
 * Implémentation de face_engine.h sur les composants IDF managés
 * `human_face_detect` + `human_face_recognition` (successeurs actuels
 * d'ESP-WHO dans le Component Registry Espressif, voir
 * hardware/README.md § "Reconnaissance faciale embarquée"). API vérifiée en
 * inspectant les en-têtes réellement publiés (idf_component.yml) :
 * - `HumanFaceDetect::run(img)` → `std::list<dl::detect::result_t>&`
 *   (chaque résultat porte `.score` et `.keypoint`, les repères utilisés par
 *   le modèle de features).
 * - `HumanFaceFeat::run(img, keypoint)` → `dl::TensorBase*` (le vecteur
 *   d'embedding brut — on n'utilise PAS `HumanFaceRecognizer`, qui gère sa
 *   propre base sur flash/SD : notre comparaison se fait nous-mêmes contre le
 *   cache local synchronisé avec l'API, voir face_cache.h).
 * - `dl::image::sw_decode_jpeg({data,len}, DL_IMAGE_PIX_TYPE_RGB888)` décode
 *   un JPEG (frame caméra ou photo téléchargée) en `img_t` RGB888 exploitable
 *   par les deux modèles ci-dessus.
 */
#include "face_engine.h"
#include "config.h"

#include <human_face_detect.hpp>
#include <human_face_recognition.hpp>
#include <dl_image_jpeg.hpp>

#include <algorithm>
#include <cmath>

namespace {

HumanFaceDetect *g_detect = nullptr;
HumanFaceFeat *g_feat = nullptr;

/** Copie un TensorBase (float ou int8 quantifié) dans un FaceEmbedding en float, déquantifiant si besoin. */
bool tensorToEmbedding(dl::TensorBase *tensor, FaceEmbedding &out) {
    if (!tensor || tensor->size <= 0) return false;

    out.resize(tensor->size);
    switch (tensor->dtype) {
        case dl::DATA_TYPE_FLOAT: {
            const float *p = tensor->get_element_ptr<float>();
            for (int i = 0; i < tensor->size; ++i) out[i] = p[i];
            return true;
        }
        case dl::DATA_TYPE_INT8: {
            const int8_t *p = tensor->get_element_ptr<int8_t>();
            float scale = powf(2.0f, tensor->exponent.get());
            for (int i = 0; i < tensor->size; ++i) out[i] = (float) p[i] * scale;
            return true;
        }
        default:
            Serial.printf("[face] dtype de sortie inattendu (%d) pour le vecteur de features\n", (int) tensor->dtype);
            return false;
    }
}

/** Détection (meilleur score) + extraction du vecteur de features sur une image RGB888 déjà décodée. */
bool extractFromImg(const dl::image::img_t &img, FaceEmbedding &outEmbedding) {
    if (!g_detect || !g_feat) return false;

    std::list<dl::detect::result_t> &results = g_detect->run(img);
    if (results.empty()) return false;

    // run() ne garantit pas un tri par score après les étapes NMS/2-stage —
    // on prend explicitement le meilleur candidat plutôt que le premier.
    auto best = std::max_element(
        results.begin(), results.end(),
        [](const dl::detect::result_t &a, const dl::detect::result_t &b) { return a.score < b.score; });

    dl::TensorBase *feat = g_feat->run(img, best->keypoint);
    return tensorToEmbedding(feat, outEmbedding);
}

} // namespace

bool faceEngineInit() {
    g_detect = new HumanFaceDetect();
    g_feat = new HumanFaceFeat();
    return g_detect != nullptr && g_feat != nullptr;
}

bool faceEngineExtractEmbeddingFromJpeg(const uint8_t *jpegData, size_t jpegLen, FaceEmbedding &outEmbedding) {
    if (!jpegData || jpegLen == 0) return false;

    dl::image::jpeg_img_t jpeg{const_cast<uint8_t *>(jpegData), jpegLen};
    dl::image::img_t img = dl::image::sw_decode_jpeg(jpeg, dl::image::DL_IMAGE_PIX_TYPE_RGB888);
    if (!img.data) return false;

    bool ok = extractFromImg(img, outEmbedding);
    free(img.data); // sw_decode_jpeg alloue son buffer de sortie, à libérer par l'appelant.
    return ok;
}

bool faceEngineExtractEmbedding(camera_fb_t *fb, FaceEmbedding &outEmbedding) {
    if (!fb) return false;
    return faceEngineExtractEmbeddingFromJpeg(fb->buf, fb->len, outEmbedding);
}

float faceEngineCompare(const FaceEmbedding &a, const FaceEmbedding &b) {
    if (a.size() != b.size() || a.empty()) return -1.0f;

    double dot = 0.0, normA = 0.0, normB = 0.0;
    for (size_t i = 0; i < a.size(); ++i) {
        dot += (double) a[i] * (double) b[i];
        normA += (double) a[i] * (double) a[i];
        normB += (double) b[i] * (double) b[i];
    }
    if (normA <= 0.0 || normB <= 0.0) return -1.0f;

    return (float) (dot / (std::sqrt(normA) * std::sqrt(normB)));
}

const EnrolledFace *faceEngineFindBestMatch(const std::vector<EnrolledFace> &cache, const FaceEmbedding &probe, float &outScore) {
    const EnrolledFace *best = nullptr;
    outScore = -1.0f;

    for (const auto &face : cache) {
        float score = faceEngineCompare(face.embedding, probe);
        if (score > outScore) {
            outScore = score;
            best = &face;
        }
    }

    if (best && outScore >= RECOGNITION_THRESHOLD) return best;
    return nullptr;
}
