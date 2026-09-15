# Auditron X

Gestion de la présence du personnel enseignant et administratif — voir
[CDC.md](CDC.md) pour le cahier des charges complet.

## Structure du dépôt

Le projet est multi-établissement : chaque établissement a sa propre instance API
et sa propre application mobile, isolées (base de données, clé de chiffrement,
URL, branding), obtenues par duplication du code de référence `api-ltm` / `mobile`.

| Dossier | Contenu |
|---|---|
| [`api-ltm/`](api-ltm) | Backend Laravel de référence (API REST/JSON, Sanctum, migrations, tests) — voir [`api-ltm/docs/API.md`](api-ltm/docs/API.md) |
| [`api-lcm/`](api-lcm) | Backend Laravel — Lycée Classique de Meiganga (port local 8001) |
| [`api-lbm/`](api-lbm) | Backend Laravel — Lycée Bilingue de Meiganga (port local 8002) |
| [`web/`](web) | Backoffice React (Vite + Tailwind) consommant l'API |
| [`mobile/`](mobile) | Application Flutter de référence (activation, scan QR + BSSID, procuration, historique) |
| [`mobile-lcm/`](mobile-lcm) | App Flutter — Lycée Classique de Meiganga (pointe vers `api-lcm`) |
| [`mobile-lbm/`](mobile-lbm) | App Flutter — Lycée Bilingue de Meiganga (pointe vers `api-lbm`) |
| [`hardware/esp32_borne/`](hardware/esp32_borne) | Firmware ESP32-S3 de référence (borne caméra+BLE) — voir [`hardware/README.md`](hardware/README.md) |
| [`hardware/esp32_borne_lcm/`](hardware/esp32_borne_lcm) | Firmware borne — Lycée Classique de Meiganga (pointe vers `api-lcm`) |
| [`hardware/esp32_borne_lbm/`](hardware/esp32_borne_lbm) | Firmware borne — Lycée Bilingue de Meiganga (pointe vers `api-lbm`) |

Config propre à chaque établissement, à vérifier/adapter dans chaque copie :
- API : `.env` (`APP_NAME`, `APP_KEY`, `APP_URL`, base de données) — chaque copie a
  déjà une clé et un nom d'app distincts.
- Mobile : `lib/services/api_client.dart` (`baseUrl`), `lib/main.dart` (titre),
  `android/app/src/main/AndroidManifest.xml` (`android:label`) et
  `android/app/build.gradle.kts` (`applicationId`) — déjà différenciés pour que
  les deux apps coexistent sur un même téléphone. **Important** : le
  `google-services.json` de chaque copie mobile correspond encore au
  `applicationId` d'origine ; il faut déclarer une nouvelle app Android dans la
  console Firebase pour chaque nouvel `applicationId` et remplacer ce fichier
  pour que les notifications push (FCM) fonctionnent.
- Hardware : `include/config.h` (`API_BASE_URL`, `BLE_DEVICE_NAME` déjà adaptés ;
  `RELAY_API_TOKEN`/`STA_SSID`/`STA_PASSWORD` marqués `TODO`, à provisionner par
  borne physique — voir [`hardware/README.md`](hardware/README.md#mise-en-service)).

## Démarrage rapide

```bash
# API (remplacer api-ltm par api-lcm ou api-lbm selon l'établissement)
cd api-ltm
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Backoffice React
cd web
npm install
npm run dev

# App mobile (remplacer mobile par mobile-lcm ou mobile-lbm selon l'établissement)
cd mobile
flutter pub get
flutter run
```
