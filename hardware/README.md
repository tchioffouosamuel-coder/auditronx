# Borne de pointage offline (§hardware)

Module matériel unique, colocalisé avec le modem, pour fiabiliser le
pointage dans les zones où internet n'est pas toujours disponible.

```
[Téléphone enseignant] --WiFi local (HTTP)--> [ESP32 "borne", WIFI_AP_STA] --WiFi modem (HTTPS)--> [API]
```

Un seul ESP32 tient les deux rôles simultanément (`WIFI_AP_STA`) :
- **AP** : point d'accès WiFi auquel le téléphone se connecte pour pointer.
  Sert aussi de vérification de localisation (le téléphone doit être
  associé à cette borne).
- **STA** : client WiFi connecté au modem/routeur, pour la remontée vers
  l'API dès que la connexion est disponible.

Le pointage est écrit sur flash (LittleFS) **avant** toute tentative
réseau, et un paquet n'est retiré de la file locale que sur confirmation
explicite de l'API (`ok` = accepté, `rejected` = invalide, inutile de
réessayer). Une réponse `retry` (ou une requête qui échoue) le laisse en
file pour le prochain cycle — rien n'est perdu en cas de coupure secteur
ou réseau.

> Version précédente : une architecture à deux ESP32 (`esp1_borne` +
> `esp2_relais`, reliés par ESP-NOW) a été remplacée par ce module unique
> depuis que le point de pointage est colocalisé avec le modem — plus
> besoin de relais radio entre deux appareils. Voir « Ce qui a changé »
> en bas de page si vous mettez à jour un déploiement existant.

## Côté API

**Aucun changement requis.** `POST /api/relay/sync` recevait déjà des
paquets `{local_id, type, teacher_token, payload, captured_at}` sans rien
supposer sur le nombre de sauts matériels entre le téléphone et l'appel
HTTP — c'était déjà l'ESP2 (le device authentifié) qui poussait ce format
exact. Le module unique est authentifié de la même façon
(`device_type = relay_gateway`) et pousse le même format ; l'API ne voit
aucune différence entre l'ancien relais à deux ESP32 et ce module unique.

- `POST /api/devices/provision-relay` (admin, `auth:sanctum`) — crée le
  `Device` (`device_type = relay_gateway`) et renvoie son token Sanctum.
  À faire une fois, avant de flasher la borne.
- `POST /api/relay/sync` (`auth:sanctum`, device relais uniquement) — reçoit
  jusqu'à 100 paquets par requête, voir
  [`RelaySyncController`](../api/app/Http/Controllers/Api/RelaySyncController.php).
  Réponse : `{"results":[{"local_id","status":"ok"|"rejected"|"retry","message"?}]}`.

Seule chose facultative : le `device_type` s'appelle encore
`relay_gateway` et le endpoint `provision-relay` — ces noms datent de
l'architecture à deux modules mais restent corrects fonctionnellement
(c'est toujours ce device qui relaie vers l'API). Renommez-les seulement
si la terminologie vous gêne ; ce n'est pas requis pour que le flow
fonctionne.

## Mise en service

1. **Provisionner la borne** (une fois, authentifié en tant qu'admin) :
   ```bash
   curl -X POST https://votre-domaine/api/devices/provision-relay \
     -H "Authorization: Bearer <token admin>" \
     -H "Content-Type: application/json" \
     -d '{"device_uuid":"esp32-borne-01"}'
   ```
   Copier le `token` renvoyé dans `esp32_borne/include/config.h` (`RELAY_API_TOKEN`).

2. **Configurer** `esp32_borne/include/config.h` :
   - `AP_SSID` / `AP_PASSWORD` : point d'accès local pour le téléphone.
   - `STA_SSID` / `STA_PASSWORD` : WiFi du modem/routeur.
   - `API_BASE_URL`, `RELAY_API_TOKEN`.

3. **Flasher** avec [PlatformIO](https://platformio.org/) :
   ```bash
   cd hardware/esp32_borne && pio run -t upload
   ```

## Contrat HTTP local (téléphone → borne)

`POST http://<ip-borne>/scan`

```json
{
  "type": "scan",
  "teacher_token": "<token Sanctum de l'enseignant, émis à l'activation de son app>",
  "payload": { "qr_code": "...", "bssid": "..." },
  "captured_at": "2026-09-04T08:12:00Z"
}
```

`type: "admin_proxy"` attend en plus `payload.enseignant_id` et
`payload.motif` (voir `AttendanceRecorder::recordProxyScan`). `captured_at`
est optionnel : si omis, la borne utilise son heure NTP propre (elle a son
propre accès au modem, donc plus besoin de synchro horaire par un second
module) ; si elle n'a encore jamais réussi de NTP au démarrage, elle
répond `503` plutôt que d'enregistrer un horodatage faux.

Réponse : `202 {"queued": true, "local_id": "..."}`. Le pointage est
définitivement acquis côté serveur après le prochain cycle de synchro de
la borne — l'app mobile ne doit pas attendre de confirmation immédiate
dans ce mode dégradé.

## Fichiers

```
hardware/
  esp32_borne/   # firmware unique (AP+STA + HTTP local + file persistante + sync API)
```

## Ce qui a changé (deux ESP32 → un seul)

Fichiers de l'ancienne architecture à supprimer si vous mettez à jour un
dépôt qui les contient encore (déjà absents de ce dépôt) :

- `hardware/esp1_borne/` (dont `include/relay_protocol.h`, les callbacks
  `onDataSent`/`onDataRecv`, la fragmentation ESP-NOW, la synchro horaire
  `TimeSync` reçue d'ESP2)
- `hardware/esp2_relais/` (dont sa propre copie de `relay_protocol.h`, son
  `onDataRecv` de réassemblage de fragments, l'envoi de `PacketAck`)

Ce qui a été conservé à l'identique dans `esp32_borne/` : le serveur HTTP
local `/scan`, l'écriture immédiate sur LittleFS avant tout envoi, le
moteur de pull périodique (`syncWithApi`), et la suppression uniquement
sur confirmation explicite (`ok`/`rejected`) de l'API.
