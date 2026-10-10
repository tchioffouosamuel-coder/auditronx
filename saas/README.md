# Auditron X — plateforme multi-établissement

Une seule API, **un seul portail web, une seule application mobile, un seul
firmware** pour tous les établissements abonnés. L'établissement est une
donnée, plus une copie du code : ouvrir un abonné coûte une commande.

Chaque établissement possède **sa propre base de données** (isolation forte,
sauvegarde et suppression par client) ; une base centrale ne contient que
l'annuaire commercial de la plateforme. L'établissement de chaque requête est
désigné par l'en-tête HTTP `X-Tenant`, l'URL étant la même pour tout le monde.

Voir [ARCHITECTURE.md](ARCHITECTURE.md) pour les choix de conception et
[MIGRATION.md](MIGRATION.md) pour la reprise des trois instances existantes
(`api-ltm`, `api-lcm`, `api-lbm`).

## Structure

| Dossier | Contenu |
|---|---|
| [`api/`](api) | API Laravel unique : routes métier (base de l'établissement) + routes plateforme (base centrale) |
| [`web/`](web) | Portail React unique : espace établissement + espace éditeur (`/plateforme`) |
| [`mobile/`](mobile) | Application Flutter unique : choix de l'établissement à l'activation |
| [`hardware/borne/`](hardware/borne) | Firmware ESP32 unique : `TENANT_CODE` dans `include/config.h` |

Les trois couches côté client envoient le même en-tête, mémorisé une fois :
le portail à la connexion, l'app à l'activation, la borne à sa mise en service.

## Démarrage

### API

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate

# 1. Base centrale (abonnés, plans, abonnements, factures, comptes éditeur)
php artisan central:migrate --seed

# 2. Un premier établissement : sa base est créée, migrée et amorcée
php artisan etablissement:create LTM "Lycée Technique de Meiganga" \
    --ville=Meiganga --plan=standard --direction-email=direction@ltm.cm

php artisan serve
```

La commande affiche le mot de passe du compte de direction **une seule fois** :
il n'est conservé que haché dans la base du client.

Sur un hébergement mutualisé (pas de droit `CREATE DATABASE`, un utilisateur
MySQL par base), créer la base depuis le panneau de l'hébergeur puis :

```bash
php artisan etablissement:create LTM "Lycée Technique de Meiganga"     --base-existante --db=u133979320_ltm     --db-user=u133979320_ltm --db-password='<mot de passe>'
```

Pour une démo locale sans MySQL, les deux connexions acceptent SQLite :

```bash
DB_CENTRAL_DRIVER=sqlite DB_CENTRAL_DATABASE=$(pwd)/database/tenants/central.sqlite \
DB_TENANT_DRIVER=sqlite php artisan central:migrate --seed
```

### Portail web

```bash
cd web
npm install
npm run dev    # proxy /api → AUDITRON_API_PROXY (défaut http://localhost:8000)
```

- `/login` : l'utilisateur choisit son établissement (liste, code, ou
  recherche par téléphone), puis s'identifie. Le choix est mémorisé.
- `/plateforme` : espace éditeur — parc d'abonnés, provisioning, suspension,
  offres et facturation.

### Application mobile

```bash
cd mobile
flutter pub get
flutter run --dart-define=AUDITRON_API_URL=http://10.0.2.2:8000/api
```

Une seule fiche Play Store, un seul `applicationId`, un seul projet Firebase :
l'enseignant choisit son établissement au premier lancement (liste, QR
d'enrôlement, code, ou son numéro de téléphone).

### Borne

Un seul firmware. Par borne, à la mise en service dans
[`hardware/borne/include/config.h`](hardware/borne/include/config.h) :
`TENANT_CODE` (code de l'établissement), `RELAY_API_TOKEN` (token du device
relais, créé dans la base de cet établissement), `STA_SSID` / `STA_PASSWORD`.

## Exploitation

```bash
# Après chaque déploiement : met à niveau le schéma de tous les abonnés
php artisan tenants:migrate

# Tâche planifiée unique, rejouée dans chaque établissement actif
php artisan tenants:run "auditron:detect-absences"

# Suspension pour non-paiement (réversible, non destructive)
php artisan etablissement:statut LCM suspendu

# Comptes éditeur
php artisan plateforme:utilisateur "Awa N." awa@auditronx.com --role=support
```

Une seule entrée cron suffit :

```
* * * * * cd /chemin/vers/api && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
cd api
vendor/bin/phpunit
```

161 tests, dont [`MultiEtablissementTest`](api/tests/Feature/MultiEtablissementTest.php)
qui couvre la résolution du locataire, le cloisonnement des données et des
jetons, le 402 d'abonnement suspendu et le provisioning complet d'un abonné.

Trois tests métier échouent, à l'identique sur l'API d'origine `api-ltm` : ils
sont antérieurs à cette architecture (voir [MIGRATION.md](MIGRATION.md#tests)).
