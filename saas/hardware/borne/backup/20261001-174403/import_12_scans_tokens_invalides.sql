-- =============================================================================
-- Import manuel des 12 scans du 01/10/2026 rejetés "Token enseignant invalide"
-- (file de la borne esp32dev-borne-02, backup 20261001-174403).
--
-- Règles reprises de l'API (AttendanceRecorder) : 1er scan = arrivée ; dernier
-- scan = départ s'il est au moins 40 min après l'arrivée. Heures en GMT+1.
-- INSERT IGNORE : un enseignant qui a DÉJÀ une présence ce jour-là est sauté
-- (rien n'est écrasé) — l'étape 1 dit lesquels, à traiter à la main.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 0. PARAMÈTRES — vérifier / corriger AVANT exécution.
--    Supprimer la ligne INSERT d'un token non identifié plutôt que deviner.
-- ---------------------------------------------------------------------------
SET @admin = NULL;   -- id de VOTRE compte dans `users` (recorded_by), ex. 1

SET @t202 = 150;     -- GADJI PHILEMON            (forte — téléphone révoqué à 07:30, scan à 07:42)
SET @t157 = 95;      -- YALDA FLORENCE            (moyenne-forte)
SET @t158 = 131;     -- Oumoul Salmata            (moyenne-forte)
SET @t146 = 39;      -- DINA DIEUDONNE            (faible — à confirmer)
SET @t171 = 46;      -- Fouba Patrick             (faible — ou 97 KEY LEA IVETTE / 62 YANDA Sawala / 130 Tokore marie)
SET @t172 = 130;     -- Tokore marie              (faible — même liste que 171, jamais la même personne)
SET @t102 = 114;     -- MOUOSSAH PEHUIE VANEL     (faible — ou 56 NYOBE JEAN DANIEL / 85 MFOPOU MANTAP Daniel)
SET @t110 = 113;     -- EBONTOU SATURNIN          (faible — ou 5 BOUTCHOUANG ; jamais la même personne que 171)

SET @ap = (SELECT id FROM access_points WHERE bssid = 'f0:24:f9:0e:1f:72' LIMIT 1);
SET @motif = 'Import manuel borne 01/10 : token n°%s introuvable, attribué manuellement';

-- ---------------------------------------------------------------------------
-- 1. CONTRÔLES (lecture seule)
-- ---------------------------------------------------------------------------
-- La borne doit être connue (sinon @ap est NULL) :
SELECT @ap AS access_point_id;

-- Noms des enseignants retenus — vérifier qu'ils correspondent :
SELECT id, nom, matricule FROM enseignants
WHERE id IN (@t202, @t157, @t158, @t146, @t171, @t172, @t102, @t110);

-- Enseignants ayant DÉJÀ une présence le 01/10 (ils seront sautés par l'étape 2) :
SELECT p.id, p.enseignant_id, e.nom, p.heure_arrivee, p.heure_depart, p.source
FROM presences p JOIN enseignants e ON e.id = p.enseignant_id
WHERE p.date = '2026-10-01'
  AND p.enseignant_id IN (@t202, @t157, @t158, @t146, @t171, @t172, @t102, @t110);

-- ---------------------------------------------------------------------------
-- 2. IMPORT
-- ---------------------------------------------------------------------------
START TRANSACTION;

INSERT IGNORE INTO presences
    (enseignant_id, date, heure_arrivee, heure_depart, source, access_point_id,
     recorded_by, reason, device_capture_at, created_at, updated_at)
VALUES
-- token 110 : 07:35:49 (arrivée) + 11:44:56 (départ)
(@t110, '2026-10-01', '2026-10-01 07:35:49', '2026-10-01 11:44:56', 'manuel', @ap, @admin, REPLACE(@motif, '%s', '110'), '2026-10-01 07:35:49', NOW(), NOW()),
-- token 157 : 07:36:16 (arrivée) + 09:35:18 / 09:35:41 (départ = le dernier)
(@t157, '2026-10-01', '2026-10-01 07:36:16', '2026-10-01 09:35:41', 'manuel', @ap, @admin, REPLACE(@motif, '%s', '157'), '2026-10-01 07:36:16', NOW(), NOW()),
-- token 202 : 07:42:12 (arrivée)
(@t202, '2026-10-01', '2026-10-01 07:42:12', NULL,                  'manuel', @ap, @admin, REPLACE(@motif, '%s', '202'), '2026-10-01 07:42:12', NOW(), NOW()),
-- token 146 : 07:51:24 (arrivée)
(@t146, '2026-10-01', '2026-10-01 07:51:24', NULL,                  'manuel', @ap, @admin, REPLACE(@motif, '%s', '146'), '2026-10-01 07:51:24', NOW(), NOW()),
-- token 158 : 08:17:18 (arrivée)
(@t158, '2026-10-01', '2026-10-01 08:17:18', NULL,                  'manuel', @ap, @admin, REPLACE(@motif, '%s', '158'), '2026-10-01 08:17:18', NOW(), NOW()),
-- token 172 : 10:21:12 (arrivée)
(@t172, '2026-10-01', '2026-10-01 10:21:12', NULL,                  'manuel', @ap, @admin, REPLACE(@motif, '%s', '172'), '2026-10-01 10:21:12', NOW(), NOW()),
-- token 171 : 11:02:34 (arrivée) + 13:20:18 (départ)
(@t171, '2026-10-01', '2026-10-01 11:02:34', '2026-10-01 13:20:18', 'manuel', @ap, @admin, REPLACE(@motif, '%s', '171'), '2026-10-01 11:02:34', NOW(), NOW()),
-- token 102 : 12:30:03 (arrivée)
(@t102, '2026-10-01', '2026-10-01 12:30:03', NULL,                  'manuel', @ap, @admin, REPLACE(@motif, '%s', '102'), '2026-10-01 12:30:03', NOW(), NOW());

-- Doit lister les lignes réellement insérées (8 au maximum) :
SELECT p.id, e.nom, p.heure_arrivee, p.heure_depart, p.reason
FROM presences p JOIN enseignants e ON e.id = p.enseignant_id
WHERE p.date = '2026-10-01' AND p.reason LIKE 'Import manuel borne 01/10 :%';

COMMIT;   -- ou ROLLBACK; si le résultat ci-dessus ne convient pas

-- ---------------------------------------------------------------------------
-- 3. ANNULATION (si une attribution s'avère fausse plus tard)
-- ---------------------------------------------------------------------------
-- DELETE FROM presences
-- WHERE date = '2026-10-01' AND reason LIKE 'Import manuel borne 01/10 : token n°146 %';
