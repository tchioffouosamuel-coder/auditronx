#pragma once

#include <Arduino.h>
#include <esp_camera.h>
#include <vector>

/**
 * Détection + reconnaissance faciale embarquées (ESP-WHO), voir
 * hardware/README.md § "Reconnaissance faciale embarquée". Interface stable
 * utilisée par main.cpp — l'implémentation (face_engine.cpp) est le seul
 * fichier à adapter si l'API exacte du composant esp-who résolu diffère de
 * ce qui est supposé ici (voir TODO dans le .cpp).
 */

// Dimension du vecteur d'embedding produit par le modèle de reconnaissance
// (MFN) — à confirmer contre le composant esp-who réellement résolu.
inline constexpr size_t FACE_EMBEDDING_DIM = 512;

using FaceEmbedding = std::vector<float>;

struct EnrolledFace {
    uint32_t enseignant_id = 0;
    String nom;
    FaceEmbedding embedding;
};

/** Charge les modèles ESP-WHO en PSRAM. À appeler une fois depuis setup(). */
bool faceEngineInit();

/**
 * Détecte le plus grand visage de la frame et calcule son embedding.
 * Retourne false si aucun visage n'est détecté ou si l'extraction échoue.
 */
bool faceEngineExtractEmbedding(camera_fb_t *fb, FaceEmbedding &outEmbedding);

/**
 * Décode un buffer JPEG (photo téléchargée depuis l'API pour l'enrôlement,
 * pas une frame caméra directe) puis calcule son embedding, même pipeline
 * que faceEngineExtractEmbedding.
 */
bool faceEngineExtractEmbeddingFromJpeg(const uint8_t *jpegData, size_t jpegLen, FaceEmbedding &outEmbedding);

/** Similarité cosine entre deux embeddings, dans [-1, 1] (1 = identique). */
float faceEngineCompare(const FaceEmbedding &a, const FaceEmbedding &b);

/** Trouve le meilleur candidat au-dessus de RECOGNITION_THRESHOLD, nullptr sinon. */
const EnrolledFace *faceEngineFindBestMatch(const std::vector<EnrolledFace> &cache, const FaceEmbedding &probe, float &outScore);
