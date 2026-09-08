#pragma once

#include <vector>
#include "face_engine.h"

/**
 * Cache local des visages enrôlés (id enseignant, nom, embedding), persisté
 * sur SD (FACE_CACHE_FILE) pour survivre à un reboot sans réseau. Voir
 * hardware/README.md § "Reconnaissance faciale embarquée".
 */

/** Charge le cache depuis la carte SD dans `out` (vide si le fichier n'existe pas encore). */
void faceCacheLoad(std::vector<EnrolledFace> &out);

/** Réécrit intégralement le fichier SD à partir de `faces`. */
void faceCacheSave(const std::vector<EnrolledFace> &faces);

/** Ajoute/remplace l'entrée d'un enseignant dans `faces` (en mémoire), sans toucher la SD. */
void faceCacheUpsert(std::vector<EnrolledFace> &faces, const EnrolledFace &face);
