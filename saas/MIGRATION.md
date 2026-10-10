# Migration des instances existantes vers la plateforme

Écrit pour qui exécutera la bascule en production.

Point de départ : cinq instances séparées (`api-ltm`, `api-lcm`, `api-lbm`,
`api-lbng` et `api-test`), plusieurs apps Flutter, plusieurs firmwares,
autant de domaines. Point d'arrivée : ce
dossier `saas/`, une API, un portail, une app, un firmware.

La bascule est **incrémentale** : à aucune étape il n'est nécessaire de couper
les trois établissements en même temps.

## 0. Geler les forks

`api-ltm` est la référence : c'est de lui que `saas/api` est issu. À partir de
maintenant, plus aucun commit dans `api-lcm` / `api-lbm` — tout se fait dans
`saas/api`. Les copies ont déjà décroché (ni journal d'audit, ni OTA des bornes, ni
horaires administratifs) ; elles retrouveront ces fonctionnalités à l'étape 2,
par les migrations manquantes.

## 1. Installer la base centrale

```bash
cd saas/api
composer install
cp .env.example .env          # puis renseigner DB_*, APP_URL, APP_KEY
php artisan key:generate      # seulement si nouvelle installation
php artisan central:migrate --seed
php artisan plateforme:utilisateur "Votre nom" vous@auditronx.com --role=super_admin
```

À renseigner une fois pour toutes dans `.env`, avant le premier import :

- `AUDITRON_ANNUAIRE_SALT` — le changer plus tard rendrait tout l'index
  téléphone → établissement illisible ;
- `AUDITRON_CREATE_DATABASE=false` si l'utilisateur SQL n'a pas le droit
  `CREATE DATABASE` (hébergement mutualisé).

Vérifier les plans créés par l'amorçage (`essai`, `standard`, `premium`) et
ajuster prix et quotas depuis `/plateforme/facturation`.

## 2. Reprendre les établissements existants

Les bases métier existantes **deviennent** les bases locataires : il n'y a ni
export ni réécriture de clés.

### Ce qui est en production aujourd'hui

Relevé dans les `.env` de l'hébergement (dossier `files/`) :

Attention à l'orthographe de Ngaoundal : le `.env` relevé dans `files/` porte
`lbgn`, mais la base réelle et le domaine en service sont `lbng` — c'est cette
dernière forme qui fait foi, et le `.env` de cette instance est donc faux.

| Code | Établissement | Base et utilisateur MySQL | Domaine actuel |
|---|---|---|---|
| `LTM` | Lycée Technique de Meiganga | `u133979320_ltm` | `api-ltm.auditronx.com` |
| `LCM` | Lycée Classique de Meiganga | `u133979320_lcm` | — (`APP_URL` resté sur localhost) |
| `LBM` | Lycée Bilingue de Meiganga | `u133979320_lbm` | — (`APP_URL` resté sur localhost) |
| `LBNG` | Lycée Bilingue de Ngaoundal | `u133979320_lbng` | `api-lbng.auditronx.com` |
| `TEST` | Lycée d'Auditron (essai) | `u133979320_test` | `api-test.auditronx.com` |

Soit **quatre établissements plus un environnement d'essai**, et non trois
comme l'annonçait l'ancien README. `TEST` est conservé comme locataire, sur le
plan `essai` : il permet de répéter une manipulation en conditions réelles sans
toucher à un vrai établissement.

Deux contraintes de cet hébergement, toutes deux prises en charge :

- **Un utilisateur MySQL par base** (`u133979320_<code>`), chacun n'ayant de
  privilèges que sur la sienne, et des mots de passe qui ne sont pas tous
  identiques. Les colonnes `db_username` / `db_password` de la table
  `etablissements` portent ces accès (le mot de passe est chiffré avec
  `APP_KEY`), et `TenantManager` les applique en même temps que le nom de la
  base. Sans ça, la plateforme ne pourrait pas lire les bases existantes.
- **Pas de droit `CREATE DATABASE`** : mettre `AUDITRON_CREATE_DATABASE=false`
  dans le `.env`, créer la base d'un nouvel abonné depuis hPanel, puis
  provisionner avec `--base-existante`.

### Déclaration

Pour chaque établissement, en déclarant la base **et ses identifiants** :

```bash
php artisan etablissement:create LTM "Lycée Technique de Meiganga"     --ville=Meiganga --plan=standard     --base-existante --sans-amorcage     --db=u133979320_ltm --db-user=u133979320_ltm --db-password='<mot de passe>'
```

- `--base-existante` : ne pas créer la base, elle est déjà là ;
- `--sans-amorcage` : ne pas créer d'accréditations ni de compte de direction,
  ils existent ;
- `--db-user` / `--db-password` : à omettre si un seul utilisateur MySQL a
  droit sur toutes les bases (serveur dédié ou VPS).

À répéter pour `LCM`, `LBM`, `LBNG` et `TEST`.

Le script [`scripts/reprise-production.sh`](scripts/reprise-production.sh) fait
les cinq déclarations, enchaîne `tenants:migrate` et contrôle qu'il lit bien
dans chaque base. Les mots de passe ne sont pas écrits dedans : il les lit dans
l'environnement, à fournir au lancement (voir son en-tête).

### Si une base refuse la connexion

La fiche de l'établissement est créée avant que sa base soit touchée : un
`Access denied` laisse donc l'établissement en `en_attente`, non servi aux
clients, et rien n'est à ressaisir. Diagnostiquer, puis réparer :

```bash
php artisan etablissement:provision LBNG --tester
php artisan etablissement:provision LBNG     --db-password='<nouveau mot de passe>' --base-existante --sans-amorcage
```

Causes par ordre de fréquence : mot de passe MySQL modifié dans le panneau de
l'hébergeur depuis le `.env` d'origine, utilisateur sans privilèges sur cette
base, base renommée. Si le `.env` de l'ancienne instance porte ce même mot de
passe refusé, c'est que **cette instance-là ne fonctionne plus non plus** : le
problème précède la migration.

Puis mettre tous les schémas à niveau — c'est ici que LCM et LBM récupèrent les
migrations qu'elles n'avaient jamais reçues (journal d'audit, OTA des bornes,
horaires administratifs) :

```bash
php artisan tenants:migrate
```

Les codes choisis ici sont **définitifs** : ils seront tapés par les
enseignants et scellés dans les bornes. Les changer impliquerait de renommer
les bases et de déplacer les fichiers.

### `APP_KEY` : rien à sauver

Les cinq instances n'utilisent pas toutes la même `APP_KEY`, ce qui serait
bloquant si des données étaient chiffrées avec. Vérifié : le seul attribut
chiffré de tout le code est `visages_embeddings.embedding`, et cette table est
supprimée par la migration `2026_09_10_210000_remove_embedded_facial_data`. La
plateforme peut donc avoir sa propre `APP_KEY` sans rendre aucune donnée
illisible.

### Au passage : l'`APP_URL` de LCM et LBM

Elle est restée à `http://localhost:8000`. Aucune conséquence constatée :
l'URL ne sert qu'à construire les liens publics des photos de pointage, et
aucune borne n'en produit. Le défaut n'aurait mordu qu'au branchement d'une
borne à caméra. La plateforme n'ayant qu'une seule `APP_URL`, il disparaît à la
bascule.

### Fichiers

Un seul dossier est réellement à déplacer : les binaires de firmware servis en
OTA aux bornes.

| Depuis | Vers |
|---|---|
| `api-<x>/storage/app/private/firmwares/` | `saas/api/storage/app/private/tenants/<code>/firmwares/` |

Les chemins stockés en base sont relatifs au disque : ils restent valides,
c'est la racine du disque qui change.

**Les photos de pointage ne sont pas concernées** : les bornes en service
n'envoient pas de `photo_base64` (vérifié sur une file réelle de borne, 34
pointages, aucun avec photo). Il n'y a donc pas de `public/scan-photos/` à
déplacer. Par précaution, vérifier quand même sur le serveur avant de passer à
la suite :

```bash
ls api-ltm/public/scan-photos api-lcm/public/scan-photos \
   api-lbm/public/scan-photos api-lbng/public/scan-photos 2>/dev/null
```

Si l'un de ces dossiers existe et n'est pas vide, le déplacer vers
`saas/api/public/tenants/<code>/scan-photos/`.

Le code qui stocke et sert ces photos est conservé — colonnes
`presences.photo_path_*`, disque `public_direct`, payload `photo_base64`
optionnel côté borne : une borne équipée d'une caméra fonctionnera sans
modification, et ses photos iront dans le dossier de son établissement.

### Annuaire téléphone → établissement

L'index central est alimenté par un observateur sur les fiches de personnel :
il ne connaît donc pas le personnel importé avant la bascule. Pour l'amorcer,
une fois les établissements déclarés :

```bash
php artisan tenants:run "auditron:detect-absences"   # ou toute commande qui ne modifie rien
```

ne suffit pas — l'observateur ne se déclenche qu'à l'écriture d'une fiche. Le
plus simple est de resauvegarder les fiches une fois par établissement, depuis
`php artisan tinker` :

```php
App\Models\Enseignant::chunk(200, fn ($lot) => $lot->each->touch());
```

Sans cette étape, la recherche « code oublié ? » ne trouvera que le personnel
créé après la bascule — ce n'est pas bloquant, c'est un confort de connexion.

## 3. Portail web

Un seul build pour tous les établissements :

```bash
cd saas/web
npm install
# .env.production : VITE_API_BASE_URL=https://api.auditronx.com/public/api
npm run build
```

Les trois `.env` par établissement disparaissent. Déployer le `dist/` sur
`app.auditronx.com` (une seule entrée DNS, un seul certificat).

Les anciens portails peuvent rester en ligne le temps de prévenir les
directions, puis être remplacés par une redirection vers le nouveau.

## 4. Application mobile

Une seule application publiée, qui remplace les trois :

```bash
cd saas/mobile
flutter build appbundle
```

`applicationId` (`com.auditronx.auditron_x_app`), libellé et
`google-services.json` sont ceux de l'app de référence : **le projet Firebase
existant reste valide**, et le TODO « déclarer une app Android par copie »
disparaît avec les copies.

Côté enseignants, la bascule se fait sans urgence : les anciennes apps
continuent de fonctionner tant que les anciennes API tournent. La nouvelle app
demande le code de l'établissement au premier lancement — prévoir de le
communiquer aux directions, ou d'afficher le QR d'enrôlement dans la salle des
professeurs.

## 5. Bornes

Un seul firmware. Par borne, avant compilation
(`hardware/borne/include/config.h`) :

- `TENANT_CODE` — code de l'établissement. **Un code erroné fait écrire les
  pointages d'un lycée dans la base d'un autre.** À vérifier avant de sceller
  le boîtier ;
- `RELAY_API_TOKEN` — inchangé : le device relais existe déjà dans la base de
  son établissement, qui est la même qu'avant ;
- `API_BASE_URL` est désormais commun (`https://api.auditronx.com/public`).

La bascule se fait borne par borne, par OTA, sans toucher aux autres.

## 6. Extinction des anciennes instances

Dans cet ordre, en vérifiant à chaque palier :

1. toutes les bornes d'un établissement remontent sur la nouvelle API ;
2. les enseignants sont passés sur la nouvelle app (contrôler les pointages du
   jour depuis le portail) ;
3. la direction utilise le nouveau portail ;
4. alors seulement, arrêter l'ancienne instance — en conservant une sauvegarde
   de sa base, même si c'est la même que celle utilisée désormais.

## Après la bascule

```bash
php artisan tenants:migrate     # à chaque déploiement
```

Une seule entrée cron remplace les trois :

```
* * * * * cd /chemin/vers/saas/api && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

`vendor/bin/phpunit` : 161 tests, 158 passent.

Les trois échecs restants échouent **à l'identique sur `api-ltm`** : ils sont
antérieurs à cette architecture et n'ont pas été traités ici, pour ne pas
mélanger une correction métier avec une migration d'architecture.

- `AttendanceScanTest::test_une_sortie_est_refusee_avant_50_minutes_de_presence`
- `PerimetreDisciplinesTest::test_il_ne_liste_que_son_departement_et_les_matieres_transversales`
- `PerimetreDisciplinesTest::test_la_direction_garde_la_main_sur_tous_les_departements`
