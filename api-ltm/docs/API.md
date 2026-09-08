# API Auditron X — routes, payloads, codes d'erreur

Base URL : `/api`. Authentification par token Bearer (Laravel Sanctum), sauf mention
contraire. Deux familles de principals peuvent être authentifiées : un utilisateur
`User` (backoffice) ou un `Enseignant`/`Device` (app mobile, poste facial) — le token
détermine automatiquement le principal, indépendamment de la route appelée.

Toutes les erreurs de validation renvoient `422` avec `{ "message": ..., "errors": {...} }`
(format standard Laravel). Les erreurs d'autorisation renvoient `403`, les ressources
introuvables `404`.

## Authentification

| Méthode | Route | Auth | Description |
|---|---|---|---|
| POST | `/login` | non | `{ email, password }` → `{ token, user }` (backoffice) |
| POST | `/logout` | oui | Révoque le token courant |
| GET | `/me` | oui | Profil de l'utilisateur/enseignant authentifié |

## Activation des devices (§4.1 revu, §4.3)

Flux à deux temps : l'enseignant s'identifie d'abord par téléphone + mot de passe.
Un enseignant `est_admin` est activé immédiatement. Sinon une demande est créée
pour l'administration, qui génère l'OTP et le remet en personne — l'enseignant
termine alors l'activation avec ce code.

| Méthode | Route | Auth | Payload | Réponse |
|---|---|---|---|---|
| POST | `/devices/request-activation` | non | `{ tel, password, device_uuid, device_type? }` | `201 { activated: true, token, device }` (admin) ou `202 { activated: false, activation_request_id, message }` (sinon) |
| POST | `/devices/activate` | non | `{ code, device_uuid, device_type? }` | `201 { token, device }` |
| POST | `/devices/{device}/revoke` | oui | — | `200 { device }` |
| POST | `/devices/provision-kiosk` | oui | `{ device_uuid, label? }` | `201 { token, device }` |
| POST | `/otp/generate` | oui | `{ enseignant_id }` | `201 { otp_id, code, expires_at }` — génération manuelle directe (hors flux de demande) |
| GET | `/devices/activation-requests?statut=en_attente\|toutes` | oui | — | Liste paginée des demandes, enseignant inclus |
| POST | `/devices/activation-requests/{id}/generate-otp` | oui | — | `201 { otp_id, code, expires_at }` — marque la demande traitée |

`code` (OTP) n'est jamais stocké en clair ni renvoyé après génération : à transmettre
à l'enseignant hors bande (en personne, accueil, etc.). Le mot de passe de connexion
mobile se définit via `POST/PUT /personnel` (`password`, `est_admin`).

## Présence (§4.3, §5.3, §7)

Horodatage toujours généré côté serveur. Chaque scan crée ou complète la ligne
`presences` du jour pour l'enseignant concerné (`heure_arrivee` puis `heure_depart`).

| Méthode | Route | Auth | Payload | Réponse |
|---|---|---|---|---|
| POST | `/attendance/scan` | Enseignant (device mobile) | `{ qr_code, bssid }` | `201 Presence` |
| POST | `/attendance/admin-proxy` | Enseignant (device mobile, rôle restreint) | `{ enseignant_id, qr_code, bssid, motif }` | `201 Presence` |
| POST | `/attendance/facial-scan` | Device (kiosk_facial) | `{ enseignant_id, score_confiance, photo_base64? }` | `201 Presence` |

Codes d'erreur spécifiques :
- `422` sur `bssid` : borne WiFi non reconnue (validation serveur uniquement, jamais
  de confiance dans les données envoyées par le client).
- `404` sur `qr_code` : point QR inconnu.
- `403` : device kiosk révoqué ou de mauvais type sur `/attendance/facial-scan`.

`/relay/sync` (voir plus bas) accepte aussi un paquet de type `facial_scan` rejouant
cette même logique — c'est le chemin normal pour une borne kiosque hors-ligne.

## Reconnaissance faciale (§5, §6.4)

Le poste de reconnaissance est aujourd'hui une borne **ESP32-S3 + caméra OV5640 +
ESP-WHO**, embarquant elle-même la détection/reconnaissance (le contrat API,
initialement pensé pour un kiosque Raspberry Pi, reste identique : seul un embedding
déjà calculé transite, jamais une image brute).

| Méthode | Route | Auth | Payload | Réponse |
|---|---|---|---|---|
| POST | `/visages/enroll` | oui | `{ enseignant_id, device_id?, embedding: number[] }` | `201 VisageEmbedding` (sans le champ embedding) |
| DELETE | `/visages/{enseignant}` | oui | — | `200` — révoque l'embedding actif (droit de suppression, §7) |
| POST | `/personnel/{enseignant}/photo` | oui | multipart `{ photo }` | `200 Enseignant` (avec `photo_url`) — photo de référence chargée depuis la plateforme web, à enrôler côté borne |
| GET | `/kiosks/manifest?since=` | Device (kiosk_facial) | — | `200 { server_time, enseignants: [{id, nom, photo_url, photo_updated_at}], embeddings: [{enseignant_id, embedding, enrolled_at}] }` |

Si `device_id` est omis sur `/visages/enroll` et que l'appelant est lui-même un
`Device` (borne kiosque), il est déduit automatiquement du token authentifié — une
borne ne peut pas s'attribuer l'enrôlement au nom d'un autre device.

L'embedding est chiffré au repos (cast Eloquent `encrypted`). Aucune image brute
n'est jamais transmise à l'API sur `/visages/enroll` — uniquement sur
`/personnel/{enseignant}/photo` (photo de référence admin, distincte de l'embedding
calculé ensuite par la borne). `/kiosks/manifest` permet à toute borne kiosque de
récupérer à la fois les photos en attente d'enrôlement local et les embeddings déjà
calculés par d'autres bornes (référentiel partagé, pas de recalcul redondant).

## Cahier de texte & fiche de progression (§4.2, §4.3, §6.3)

| Méthode | Route | Auth | Payload | Réponse |
|---|---|---|---|---|
| GET | `/cahier-texte/{enseignant}` | oui | — | Liste paginée des entrées |
| POST | `/cahier-texte` | oui | `{ enseignant_id, emploi_du_temps_id, date, contenu, reference_programme? }` | `201 CahierTexteEntree` |
| GET | `/fiche-progression` | oui | query `classe_id?`, `discipline_id?`, `annee_scolaire?` | Liste `{ classe, discipline, annee_scolaire, nb_seances_prevues, nb_seances_realisees, taux_avancement, en_retard }` |

Le taux d'avancement est calculé à la volée à partir de `cours_validation` (statut
`fait`) rapporté à `programmes.nb_seances_prevues` — pas de table dédiée.

## Gestion (§4.2, §4.3) — équivalent JSON des routes web existantes

Ressources REST standard (`index`, `store`, `show`, `update`, `destroy`), toutes
authentifiées :

| Ressource | Route de base | Contrôleur | Notes |
|---|---|---|---|
| Personnel | `/personnel` | `EnseignantController` | `index` scopé par accréditation (`AccessibleEnseignants`) ; filtres `?section=`, `?q=` |
| Classes | `/classes` | `ClasseController` | — |
| Disciplines | `/disciplines` | `DisciplineController` | — |
| Emplois du temps | `/emplois` | `EmploiDuTempsController` | `422` si conflit de créneau (même enseignant ou même classe) |
| Signalements | `/signalements` | `SignalementController` | `POST /signalements/bulk` — création groupée `{ enseignant_ids[], date, motif, duree_jours? }` |
| Jours fériés | `/feries` | `FerieController` | `date` unique |
| Accréditations | `/accreditations` | `AccreditationController` | `groupe='*'` = accès total ; sinon restreint à la section |

## Tableau de bord (§4.2)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/dashboard?date=` | `{ date, effectif, presents, absents, retardataires, classement_par_section[] }` |

## Retards & bilans (§4.2)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/retards/parametres` | `{ tolerance_minutes }` |
| PUT | `/retards/parametres` | `{ tolerance_minutes }` |
| GET | `/retards?debut=&fin=&section=` | Liste des retards cumulés sur la période |
| GET | `/retards/bilan-cumule?debut=&fin=&section=` | Télécharge un PDF (`application/pdf`) |
| GET | `/retards/bilan/{enseignant}?debut=&fin=` | Fiche individuelle PDF |

Le retard est calculé par rapport au premier cours du jour de l'enseignant
(`emploi_du_temps`), au-delà du seuil de tolérance configuré.

## Assiduité & rapports (§4.2)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/assiduite/stats?debut=&fin=&section=` | Taux d'assiduité par enseignant |
| GET | `/assiduite/journal?date=&section=` | Journal des présences d'un jour |
| GET | `/assiduite/personnel-inactif?jours=N` | Enseignants sans pointage depuis N jours |
| GET | `/statistiques/export-zip?debut=&fin=&section=` | ZIP de bilans PDF individuels (`application/zip`) |

## Correcteur de présences (§4.2)

| Méthode | Route | Payload |
|---|---|---|
| GET | `/presences/anomalies?date=&section=` | — → `{ date, anomalies: [{ type: depart_manquant\|pointage_manquant, enseignant_id, presence_id }] }` |
| POST | `/presences/corriger` | `{ corrections: [{ enseignant_id, date, heure_arrivee?, heure_depart?, motif }] }` — source `manuel`, `recorded_by`/`reason` journalisés |

## Validation des présences (§4.2)

| Méthode | Route | Payload |
|---|---|---|
| GET | `/presences/validation?date=&classe_id=` | Calendrier des cours du jour avec statut |
| POST | `/presences/validation/toggle` | `{ emploi_du_temps_id, date }` → bascule `fait`/`non_fait` |

## Alertes (§4.2)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/absences/alertes` | Historique `AbsenceAlertLog`, scopé par accréditation |

La commande `php artisan auditron:detect-absences` (planifiée quotidiennement à
18h, `routes/console.php`) incrémente `absence_checkpoints` et journalise une
alerte au-delà de 3 absences consécutives (`AbsenceDetectorService`).

## Administration des appareils (§4.2)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/devices?device_type=&revoked=` | Vue des activations OTP / devices |
| GET, POST, PUT, DELETE | `/access-points` | Bornes WiFi autorisées |
| GET, POST, PUT, DELETE | `/qr-points` | Points QR (code généré serveur) |

## Import en masse (§4.2)

| Méthode | Route | Payload |
|---|---|---|
| POST | `/personnel/import` | `{ enseignants: [{ nom, matricule, email?, ... }] }` → `{ crees, erreurs[] }` (skip des matricules déjà existants) |
