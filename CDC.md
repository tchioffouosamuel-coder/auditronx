# Cahier des charges — Auditron X

Application mobile Flutter + Backoffice (React + API Laravel)

Version 1.0 — Septembre 2026

---

## 1. Contexte et objectifs

Auditron X gère la présence du personnel enseignant et administratif d'un
établissement scolaire. La version actuelle repose sur des bornes ESP32 équipées de
lecteurs d'empreinte/RFID. Ce cahier des charges couvre la **refonte du canal de
présence** (application mobile Flutter + QR code papier + preuve réseau) ainsi que la
**modernisation du backoffice**, aujourd'hui en Blade/Laravel monolithique, vers une
architecture découplée **API Laravel + interface web React**.

Objectifs :

1. Remplacer le matériel dédié (capteurs, firmware, bornes) par une identité logicielle
   portée par le téléphone de chaque enseignant.
2. Fiabiliser l'horodatage (serveur, jamais un appareil de terrain).
3. Conserver à l'identique toute la richesse fonctionnelle du backoffice existant
   (tableau de bord, gestion du personnel, emplois du temps, retards, signalements,
   rapports) en la portant sur une architecture API + SPA.
4. Poser une fondation extensible : cahier de texte numérique avec fiche de
   progression, puis horizons ultérieurs (présence élèves, notes, gestion
   administrative et financière).

### Hors périmètre de ce cahier des charges

- Canal SMS / USSD (écarté)
- Renfort réseau Niveau 2 (boîtier local signant un nonce) — à cadrer séparément si
  retenu
- Empreinte digitale / RFID — canal legacy, maintenu en coexistence pendant la
  migration puis décommissionné (voir [MIGRATION_TECHNIQUE.md](MIGRATION_TECHNIQUE.md))

> La reconnaissance faciale (§5) est un canal **matériel supplémentaire**, distinct du
> legacy empreinte/RFID : elle n'est pas décommissionnée, elle fait partie du périmètre
> cible comme canal de secours supervisé.

---

## 2. Architecture générale

```
┌─────────────────────┐        ┌──────────────────────┐        ┌─────────────────────┐
│   App mobile         │  HTTPS │   API Laravel          │  HTTP  │   Backoffice React    │
│   Flutter             │───────▶│   (backend, unique     │◀──────▶│   (SPA)               │
│   (enseignants)       │        │   source de vérité)    │        │   (direction, admin)  │
└─────────────────────┘        └──────────┬───────────┘        └─────────────────────┘
                                              ▲
                                              │ HTTPS
┌─────────────────────┐                      │
│   Poste reconnaissance│──────────────────────┘
│   faciale             │
│   (Raspberry Pi 4B +   │
│   Camera V2 8MP NoIR)  │
└─────────────────────┘
                                              │
                                              ▼
                                     ┌─────────────────┐
                                     │  Base de données  │
                                     │  (MySQL/SQLite)   │
                                     └─────────────────┘
```

- **App Flutter** : utilisée par chaque enseignant sur son propre téléphone. Scanne le
  QR papier, lit le BSSID de la borne WiFi, envoie la présence à l'API.
- **Poste de reconnaissance faciale** : boîtier Raspberry Pi 4B + caméra, positionné à
  un point supervisé (accueil, secrétariat). Canal de secours pour les enseignants sans
  téléphone disponible (voir §5).
- **API Laravel** : expose toute la logique métier (présence, retards, signalements,
  emplois du temps, cahier de texte, rapports) en REST/JSON, avec authentification par
  token (Sanctum). Ne rend plus de vues Blade pour le backoffice.
- **Backoffice React** : consomme l'API. Porte l'ensemble des écrans de gestion
  aujourd'hui en Blade (dashboard, personnel, emplois du temps, retards, signalements,
  rapports, contrôle d'accès).

---

## 3. Acteurs et rôles

| Rôle | Périmètre | Interface |
|---|---|---|
| Enseignant / personnel administratif | Scan de sa propre présence | App Flutter |
| Chef de section / censeur (accréditation restreinte) | Enseignants de sa section uniquement, scan par procuration | App Flutter (mode procuration) + Backoffice React (lecture) |
| Direction / Administration (accréditation `Administration` ou `*`) | Tous les enseignants, toutes les classes, toutes les disciplines | Backoffice React (accès complet) |

Le périmètre de chaque rôle reprend la logique déjà en place dans
[`AccessibleEnseignants`](app/Http/Controllers/Traits/AccessibleEnseignants.php) :
accréditation par `groupe` (section) et par `niveau` (1 à 4, classes/disciplines
accessibles). Cette table de règles est reprise telle quelle côté API.

---

## 4. Spécifications fonctionnelles

### 4.1 Application mobile Flutter

| Fonction | Description |
|---|---|
| Activation par OTP | L'enseignant saisit un code fourni par l'administration ; l'app enregistre son `device_uuid` et l'associe à son compte via l'API |
| Scan de présence | Lecture du QR papier fixe au point de contrôle + lecture du BSSID de la borne WiFi connectée (`network_info_plus`) ; envoi à l'API, qui horodate côté serveur |
| Historique personnel | Consultation de ses propres présences/retards du mois en cours |
| Mode procuration (rôle restreint) | Écran dédié : sélection de l'enseignant concerné, scan du QR, motif obligatoire, envoi à l'API avec `source=admin_proxy` |
| Notifications | Réception d'une notification en cas de scan effectué en son nom par un tiers (procuration) |

**Contraintes** : connexion WiFi active requise au moment du scan (pas de mode
hors-ligne dans ce périmètre) ; permission `ACCESS_FINE_LOCATION` sur Android ;
limitation connue sur iOS pour la lecture du BSSID (entitlement Apple à obtenir).

### 4.2 Backoffice React (SPA)

Portage à l'identique de la logique déjà présente dans le Laravel actuel, consommée
via l'API plutôt que rendue en Blade.

| Module | Écrans / fonctions | Contrôleur Laravel de référence |
|---|---|---|
| Tableau de bord | KPIs du jour (présents/absents/retardataires), classements d'assiduité/ponctualité/retards par section, graphiques de présence | `DashboardController` |
| Gestion du personnel | Liste, fiche, création/édition, import en masse (JSON), filtrage par section/rôle | `PersonnelController`, `EnseignantImportController` |
| Emplois du temps | Vue liste et vue grille par enseignant, création/édition de cours, détection de conflits de créneaux | `EmploisController` |
| Retards & bilans | Paramétrage des seuils de tolérance, génération de bilans PDF (cumulé, fiche individuelle) | `RetardsController` |
| Assiduité & rapports | Statistiques d'assiduité par section, journal des présences, liste du personnel inactif, exports PDF groupés (ZIP) | `AssiduiteController`, `StatistiquesController` |
| Signalements & justificatifs | Création individuelle / multiple / globale, motifs prédéfinis, gestion des jours fériés (propagation automatique) | `SignalementController`, `FerieController` |
| Correcteur de présences | Prévisualisation et correction groupée des anomalies de pointage | `PresenceCorrecteurController` |
| Validation des présences | Calendrier de validation, bascule du statut d'un cours (fait/non fait) | `PresenceValidationController` |
| **Cahier de texte numérique** *(nouveau)* | Saisie du contenu de séance par l'enseignant (ou l'administration), référence au programme officiel, **fiche de progression** par discipline/classe (taux d'avancement du programme), consultable par la direction | *À construire — s'appuie sur `CoursValidation` existant, aujourd'hui limité à un statut fait/non fait* |
| Contrôle d'accès par rôle | Gestion des accréditations (groupe, niveau) | Modèle `Accreditation`, trait `AccessibleEnseignants` |
| Alertes | Historique des notifications d'absences répétées | `AbsenceDetectorService`, `AbsenceAlertLog` |
| Administration des appareils | Vue des activations OTP, révocation d'un `device_uuid`, gestion des points QR et des BSSID autorisés | *À construire (remplace `CommandControlController`)* |

#### Détail — Cahier de texte numérique et fiche de progression

- L'enseignant saisit, après (ou avant) chaque séance, le contenu réellement traité.
- Chaque entrée est rattachée à un créneau de l'emploi du temps (`emploi_du_temps`),
  une discipline et une classe — réutilise les relations déjà en place.
- La **fiche de progression** agrège ces entrées par discipline/classe/période et
  calcule un taux d'avancement par rapport au programme officiel déclaré en amont
  (nombre de séances prévues au programme vs séances réalisées et validées).
- La direction consulte la fiche de progression par classe, par discipline ou par
  enseignant, avec alerte visuelle en cas de retard significatif sur le programme.
- Ce module remplace et étend le simple statut « fait / non fait » de
  `CoursValidation`, qui devient la brique de base sur laquelle il s'appuie.

### 4.3 API Laravel

- Authentification par token (Laravel Sanctum), un token par device (app) et un token
  par session (backoffice).
- Toutes les routes web Blade de gestion (`personnel`, `emplois`, `signalement`,
  `ferie`, `assiduite`, `retards`, `statistiques`, `presences`, `correcteur-presences`)
  sont exposées en équivalent JSON sous `/api/...`, avec la même logique métier.
- Nouvelles routes dédiées au canal de présence :

| Endpoint | Rôle |
|---|---|
| `POST /api/devices/activate` | Active un device par OTP |
| `POST /api/otp/generate` | Génère un OTP pour un enseignant (action admin) |
| `POST /api/attendance/scan` | Reçoit un scan de présence (app), valide le BSSID, horodate côté serveur |
| `POST /api/attendance/admin-proxy` | Scan par procuration (rôle restreint), motif obligatoire |
| `POST /api/attendance/facial-scan` | Reçoit une reconnaissance du poste Raspberry Pi (`device_id`, `enseignant_id`, `score_confiance`), horodate côté serveur |
| `POST /api/visages/enroll` | Enregistre l'embedding facial de référence d'un enseignant |
| `GET /api/cahier-texte/{enseignant}` | Historique du cahier de texte d'un enseignant |
| `POST /api/cahier-texte` | Enregistre une entrée de cahier de texte |
| `GET /api/fiche-progression` | Taux d'avancement par classe/discipline/période |

- Les exports PDF (DomPDF) restent générés côté Laravel et téléchargés depuis le
  backoffice React via un lien signé ou un endpoint de streaming.

---

## 5. Partie matérielle — poste de reconnaissance faciale

### 5.1 Objectif

Canal de secours **supervisé** pour les enseignants sans téléphone disponible
(oublié, éteint, en panne), en remplacement de l'idée initialement écartée d'un
terminal en libre accès (cf. l'expérience négative du terminal empreinte/RFID
« CampusPass », vandalisé en usage non supervisé). Ce poste est donc positionné à un
point supervisé de l'établissement (accueil, secrétariat, loge) — jamais en accès
libre — et reste un canal secondaire : le scan QR + application reste le canal
principal.

### 5.2 Matériel

| Composant | Référence | Rôle |
|---|---|---|
| Calculateur | Raspberry Pi 4B (4 Go RAM recommandé) | Exécute la capture, la reconnaissance faciale et l'appel à l'API |
| Caméra | Raspberry Pi Camera Module V2 8MP NoIR | Capture du visage ; variante NoIR (sans filtre infrarouge) pour un fonctionnement correct en faible luminosité |
| Éclairage IR | Illuminateur infrarouge complémentaire (LEDs 850 nm) | La caméra NoIR n'embarque pas sa propre source infrarouge : un illuminateur externe est nécessaire pour exploiter sa sensibilité IR de nuit ou en intérieur peu éclairé |
| Écran (optionnel) | Petit écran ou LED de confirmation | Retour visuel immédiat (reconnu / non reconnu) à l'enseignant |
| Boîtier | Boîtier mural ou de bureau, fixation au point supervisé | Protection physique de base (le poste étant supervisé, pas de blindage anti-vandalisme requis) |

### 5.3 Fonctionnement

1. Enrôlement préalable de chaque enseignant : capture de quelques photos de
   référence via le poste ou le backoffice, génération d'un **embedding facial**
   (vecteur numérique), stocké côté serveur — jamais l'image brute une fois
   l'embedding calculé, sauf besoin explicite de ré-enrôlement.
2. Au passage devant la caméra, le Raspberry Pi détecte un visage, calcule son
   embedding localement et le compare aux embeddings connus (seuil de similarité
   configurable).
3. En cas de correspondance, le poste envoie à l'API `{device_id, enseignant_id,
   score_confiance}` — jamais l'image — via `POST /api/attendance/facial-scan`.
   L'horodatage est généré côté serveur, comme pour les autres canaux.
4. En cas de doute (score sous le seuil) ou d'échec de reconnaissance, le poste
   invite l'enseignant à utiliser l'application ou redirige vers le scan par
   procuration.

### 5.4 Pile logicielle embarquée

| Couche | Choix |
|---|---|
| OS | Raspberry Pi OS (64 bits) |
| Capture | `picamera2` (pilote officiel de la Camera Module V2) |
| Détection / reconnaissance faciale | Bibliothèque de reconnaissance faciale embarquée (ex. `face_recognition`/`dlib`, ou modèle léger optimisé ARM) — à arbitrer en phase de cadrage selon les performances mesurées sur le Pi 4B |
| Communication | Client HTTPS vers l'API Laravel, avec authentification par device (même mécanisme que `devices.device_uuid`) |

### 5.5 Exigences spécifiques

- **Performance** : reconnaissance en moins de 2 secondes dans des conditions
  d'éclairage normales.
- **Données biométriques** : les embeddings sont des données sensibles — stockage
  chiffré côté serveur, consentement explicite de l'enseignant à l'enrôlement,
  procédure de suppression de son embedding sur demande.
- **Supervision obligatoire** : ce poste n'est jamais déployé en accès libre non
  surveillé, leçon directement tirée du retour d'expérience sur le terminal
  précédent.
- **Dégradation gracieuse** : en cas de mauvaise luminosité ou d'échec de
  reconnaissance répété, le poste ne bloque jamais l'enseignant — il renvoie vers
  l'application ou le scan par procuration.

---

## 6. Modèle de données

### 6.1 Tables existantes (conservées, contrat inchangé)

| Table | Champs clés | Rôle |
|---|---|---|
| `enseignants` | `nom`, `matricule`, `email`, `fonction`, `section`, `grade`, `tel`, `poste`, `rfid_uid` *(déprécié)* | Fiche personnel |
| `presences` | `enseignant_id`, `date`, `heure_arrivee`, `heure_depart` | Présence quotidienne — **contrat consommé par tous les modules, à ne pas casser** |
| `emploi_du_temps` | `enseignant_id`, `classe_id`, `discipline_id`, `jour`, `heure_debut`, `heure_fin`, `salle`, `type_cours` | Emploi du temps |
| `classes` | `nom`, `code`, `niveau`, `specialite`, `effectif` | Classes d'élèves |
| `disciplines` | `nom`, `code`, `coefficient`, `departement` | Matières enseignées |
| `cours_validation` | `enseignant_id`, `emploi`, `date`, `status` | Statut fait/non fait par séance — base du cahier de texte |
| `signalements` | `enseignant_id`, `date`, `motif`, `duree_jours` | Justificatifs d'absence/retard |
| `feries` | `date`, `libelle`, `description` | Jours fériés |
| `absence_checkpoints`, `absence_alert_logs` | — | Suivi de la détection d'absences répétées |
| `accreditations` | `groupe`, `niveau` | Périmètre d'accès par rôle |
| `users` | `name`, `email`, `accreditation_id` | Comptes du backoffice |

### 6.2 Nouvelles tables (canal QR + app)

```
devices            id, teacher_id, device_uuid, device_type ('mobile'|'kiosk_facial'),
                    activated_at, otp_id, revoked_at
otps               id, teacher_id, code_hash, expires_at, used_at
access_points      id, bssid, ssid, label, etablissement_id
qr_points          id, code, label, etablissement_id
```

`presences` est étendue de façon additive (voir §2.1 de
[MIGRATION_TECHNIQUE.md](MIGRATION_TECHNIQUE.md)) : `source` (`app_mobile`,
`admin_proxy`, `reconnaissance_faciale`, `manuel`), `access_point_id`, `device_id`,
`recorded_by`, `on_behalf_of`, `reason`.

### 6.3 Nouvelles tables (cahier de texte numérique)

```
programmes            id, discipline_id, classe_id, annee_scolaire, nb_seances_prevues
cahier_texte_entrees  id, enseignant_id, emploi_du_temps_id, date, contenu,
                       reference_programme, created_by
```

La fiche de progression est calculée à la volée (agrégation des entrées validées par
rapport à `programmes.nb_seances_prevues`), sans table dédiée supplémentaire dans un
premier temps.

### 6.4 Nouvelle table (reconnaissance faciale)

```
visages_embeddings  id, enseignant_id, device_id (kiosk d'enrôlement), embedding,
                     enrolled_at, revoked_at
```

L'embedding est stocké chiffré. Aucune image brute n'est conservée après calcul de
l'embedding, sauf procédure explicite de ré-enrôlement.

---

## 7. Exigences non fonctionnelles

- **Sécurité** : tokens Sanctum avec expiration, retrait immédiat du mot de passe en
  dur actuellement présent dans `CommandControlController`, validation BSSID côté
  serveur uniquement (jamais de confiance dans une donnée envoyée par le client sans
  vérification).
- **Fiabilité de l'horodatage** : toute date/heure de présence est générée par le
  serveur au moment de la réception de la requête, jamais par l'appareil de terrain.
- **Disponibilité** : le backoffice React et l'API doivent rester utilisables même si
  une partie des enseignants n'a pas encore basculé sur le nouveau canal (coexistence
  avec RFID pendant la période de migration).
- **Localisation** : interface et documents (PDF, notifications) en français.
- **Traçabilité** : toute action de procuration ou de correction manuelle de présence
  est journalisée avec l'auteur, la date et le motif.
- **Compatibilité mobile** : Android prioritaire (permissions BSSID standard) ; iOS en
  best-effort selon l'obtention de l'entitlement Apple nécessaire à la lecture du
  BSSID.
- **Données biométriques** (poste de reconnaissance faciale) : embeddings chiffrés au
  repos, consentement explicite à l'enrôlement, droit de suppression sur demande de
  l'enseignant, poste toujours déployé en zone supervisée (jamais en libre accès).

---

## 8. Stack technique

| Brique | Technologie |
|---|---|
| App mobile | Flutter (Android prioritaire, iOS best-effort), `network_info_plus` pour le BSSID |
| Poste de reconnaissance faciale | Raspberry Pi 4B, Camera Module V2 8MP NoIR + illuminateur IR, `picamera2`, bibliothèque de reconnaissance faciale embarquée |
| Backoffice | React (SPA), consommation API REST/JSON |
| Backend | Laravel (API Sanctum), MySQL en production / SQLite en développement |
| Génération PDF | DomPDF (conservé, inchangé) |
| Notifications | Mail (conservé) ; WhatsApp (Twilio) en cours de finalisation, hors périmètre garanti de ce cahier des charges |

---

## 9. Livrables attendus

1. Application Flutter (activation OTP, scan QR + BSSID, mode procuration)
2. API Laravel exposant l'ensemble des modules du §4.3
3. Backoffice React couvrant l'intégralité des écrans du §4.2, y compris le nouveau
   module cahier de texte / fiche de progression
4. Poste de reconnaissance faciale opérationnel (Raspberry Pi 4B + Camera V2 8MP NoIR),
   avec procédure d'enrôlement et intégration à l'API (§5)
5. Migrations de base de données (nouvelles tables + extension additive de `presences`)
6. Jeu de tests d'intégration garantissant qu'un scan via un nouveau canal (app ou
   reconnaissance faciale) produit une ligne `presences` strictement compatible avec
   l'existant
7. Documentation d'API (routes, payloads, codes d'erreur)

---

## 10. Phasage

Le phasage détaillé (fondations backend, app mobile, scan par procuration, pilote,
généralisation) est décrit dans [MIGRATION_TECHNIQUE.md](MIGRATION_TECHNIQUE.md). Le
présent cahier des charges y ajoute :

- une phase de portage du backoffice vers React, qui peut être menée en parallèle des
  phases 1-3 de la migration puisqu'elle ne dépend que de l'existence d'une API — pas
  du nouveau canal de présence ;
- une phase dédiée au poste de reconnaissance faciale (prototypage matériel,
  enrôlement pilote, intégration API), à mener après la stabilisation du canal
  app + QR (phase 4 de la migration), puisqu'elle en réutilise l'infrastructure
  d'identité (`teacher_id`, `devices`).

## 11. Critères d'acceptation

- Un enseignant peut s'activer, scanner sa présence, et la voir apparaître dans le
  backoffice React sans écart avec le comportement actuel du dashboard Blade.
- Un rôle restreint peut scanner en procuration, avec motif obligatoire et
  notification envoyée à l'enseignant concerné.
- Tous les exports PDF existants (bilans, statistiques, journal) restent
  fonctionnels et identiques dans leur contenu.
- La fiche de progression affiche, pour une classe et une discipline données, le taux
  de séances réalisées par rapport au programme prévu.
- Aucune régression sur le contrôle d'accès par rôle (accréditation groupe/niveau).
- Un enseignant enrôlé est reconnu par le poste facial en moins de 2 secondes dans des
  conditions d'éclairage normales, et sa présence apparaît dans le backoffice avec
  `source=reconnaissance_faciale`.
