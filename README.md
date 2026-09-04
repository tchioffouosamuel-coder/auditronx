# Auditron X

Gestion de la présence du personnel enseignant et administratif — voir
[CDC.md](CDC.md) pour le cahier des charges complet.

## Structure du dépôt

| Dossier | Contenu |
|---|---|
| [`api/`](api) | Backend Laravel (API REST/JSON, Sanctum, migrations, tests) — voir [`api/docs/API.md`](api/docs/API.md) |
| [`web/`](web) | Backoffice React (Vite + Tailwind) consommant l'API |
| [`mobile/`](mobile) | Application Flutter (activation, scan QR + BSSID, procuration, historique) |

## Démarrage rapide

```bash
# API
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Backoffice React
cd web
npm install
npm run dev

# App mobile
cd mobile
flutter pub get
flutter run
```
