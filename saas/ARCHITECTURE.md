# Architecture

Écrit pour les développeurs qui interviendront sur la plateforme.

## Le choix de départ

Avant : une copie complète du code par établissement (`api-ltm`, `api-lcm`,
`api-lbm`, trois apps Flutter, trois firmwares). Chaque fonctionnalité devait
être reportée N fois, et les copies avaient déjà décroché — `api-lcm` n'avait
ni journal d'audit, ni OTA des bornes, ni horaires administratifs.

Après : **une base de code, N bases de données**. Deux options étaient
possibles ; celle retenue est la seconde :

| | Base unique + `etablissement_id` | **Une base par établissement** |
|---|---|---|
| Code métier à reprendre | 47 migrations, ~25 modèles, tous les contrôleurs | aucun |
| Risque de fuite entre clients | un `where` oublié suffit | nul par construction |
| Reprise des bases existantes | export/import, réécriture des clés | la base devient le locataire |
| Chiffres consolidés | triviaux | une requête par établissement |

Le facteur décisif est le premier : le code métier (contrôleurs, services,
exports PDF, détection d'absences) n'a **pas** été modifié. Il continue
d'écrire `Enseignant::count()` sans savoir chez qui il tourne.

## Comment ça bascule

`config('database.default')` vaut `central` hors contexte, et `tenant` dès
qu'un établissement est initialisé. Les modèles métier ne déclarent aucune
connexion et suivent donc la bascule ; seuls ceux de `App\Models\Central`
épinglent `central`.

[`App\Tenancy\TenantManager`](api/app/Tenancy/TenantManager.php) fait la
bascule et, avec la base, tout ce qui en dépend :

| Ce qui bascule | Pourquoi |
|---|---|
| `database.connections.tenant.database` | la base du client |
| `database.default` | pour que les modèles métier suivent sans modification |
| `cache.prefix` | sinon une clé `dashboard` servirait les chiffres d'un autre lycée |
| racine des disques `local`, `public`, `public_direct` | photos de pointage et firmwares rangés par établissement |
| `app.timezone` | les pointages d'un lycée s'affichent dans son fuseau |
| `app.name` | en-têtes des PDF |

Deux précautions non évidentes :

- **La connexion n'est purgée que si la base change réellement.** Une purge
  ferme la connexion PDO, donc annule la transaction en cours ; purger à
  chaque requête serait à la fois coûteux et destructeur.
- **Le contexte n'est refermé que par celui qui l'a ouvert**
  (`initialisePourLaRequete` / `termineLaRequete`). Une commande qui boucle sur
  les établissements, ou la base des tests, ouvre le contexte en amont : si la
  fin de la première requête HTTP le refermait, la suivante repartirait sur la
  base centrale.

Hors contexte, la connexion `tenant` n'a pas de nom de base : toute requête
métier échoue franchement, ce qui est voulu — mieux vaut une erreur qu'une
lecture silencieuse dans la mauvaise base.

### Identifiants par base

Un établissement peut porter ses propres `db_username` / `db_password` : sur un
hébergement mutualisé (hPanel, cPanel), on ne crée pas un utilisateur ayant
droit sur toutes les bases — chaque base vient avec le sien, qui n'a de
privilèges que sur elle. C'est le cas des instances Auditron en production
(`u133979320_ltm`, `u133979320_lcm`, …), et sans ces deux colonnes la
plateforme ne pourrait pas reprendre les bases existantes.

Le mot de passe est chiffré avec `APP_KEY` et exclu de la sérialisation : la
base centrale contient les accès aux bases de **tous** les clients, c'est
l'information la plus sensible de la plateforme, et une sauvegarde SQL dérobée
ne doit pas les livrer. Laissés vides, les identifiants du `.env` s'appliquent
— le cas d'un serveur dont on maîtrise MySQL.

## Résolution de l'établissement

URL unique. [`IdentifieEtablissement`](api/app/Http/Middleware/IdentifieEtablissement.php)
lit, dans l'ordre :

1. l'en-tête `X-Tenant` (le cas normal : portail, app, borne) ;
2. un champ `etablissement` de la requête (replis : formulaire, test manuel) ;
3. le sous-domaine, **désactivé par défaut** — prêt pour le jour où
   `ltm.auditronx.com` serait souhaité, sans toucher au code.

Réponses d'échec, toutes distinctes et toutes exploitables par les clients :

| Cas | Statut | `erreur` |
|---|---|---|
| en-tête absent | 400 | `etablissement_absent` |
| code inconnu, ou établissement pas encore provisionné | 404 | `etablissement_inconnu` |
| abonnement suspendu | 402 | `abonnement_suspendu` |
| établissement archivé | 403 | `etablissement_inactif` |

Le middleware est **inséré dans la liste de priorité avant
`AuthenticatesRequests`** (voir [`bootstrap/app.php`](api/bootstrap/app.php)).
Sans cela, `SubstituteBindings` — présent dans le groupe `api`, donc exécuté
avant les intergiciels de route — chercherait le `{enseignant}` d'une URL dans
la base centrale et répondrait 404 sur des fiches existantes.

## Les deux familles de routes

| Préfixe | Base | Public | Fichier |
|---|---|---|---|
| `/api/*` | établissement | personnel de l'établissement | [`routes/api.php`](api/routes/api.php) |
| `/api/central/*` | centrale | personnel Auditron | [`routes/central.php`](api/routes/central.php) |

`routes/api.php` est resté **identique** à celui de l'API mono-établissement, à
deux routes près (`/api/etablissement` et `/api/etablissement/abonnement`) : le
middleware `tenant` y est attaché depuis `bootstrap/app.php`. Les évolutions
métier futures restent donc lisibles dans l'historique.

Trois routes de `/api/central` sont publiques, parce que les clients en ont
besoin avant de savoir quel établissement interroger : `GET /catalogue`,
`POST /annuaire/resolve`, `POST /login`.

Les frontières d'autorisation sont volontairement nettes :

- `platform` : un token d'établissement est un token Sanctum valide ; sans ce
  contrôle, la direction d'un lycée pourrait lire la liste des autres abonnés ;
- `platform:admin` : créer, provisionner, facturer, supprimer ;
- un compte éditeur n'est **pas** authentifiable sur les routes métier d'un
  client — le support qui a besoin de voir des données doit y être invité comme
  utilisateur de cet établissement, pour que le journal d'audit du client reste
  lisible.

## Cycle de vie d'un abonné

[`ProvisionneurEtablissement`](api/app/Tenancy/ProvisionneurEtablissement.php),
appelé indifféremment par la commande `etablissement:create` et par le portail
éditeur — un client ouvert par l'un ou par l'autre est strictement identique :

1. `CREATE DATABASE auditron_<code>` (ou fichier SQLite en local) ;
2. dossiers de stockage `tenants/<code>` ;
3. migrations de `database/migrations/tenant` ;
4. amorçage : accréditations *Direction* et *Surveillance générale*, compte de
   direction avec mot de passe tiré au hasard — un mot de passe commun à tous
   les clients provisionnés serait un défaut de sécurité, pas un confort ;
5. `statut = actif`, `provisionne_le` renseigné.

Tout y est idempotent : un provisioning interrompu se rejoue.

Sur un hébergement mutualisé sans droit `CREATE DATABASE` :
`AUDITRON_CREATE_DATABASE=false`, base créée à la main, puis
`etablissement:create --base-existante` (et `db_name` si l'hébergeur impose son
nommage).

## Suspendre n'est pas supprimer

Le statut de l'établissement est la seule chose que regarde le middleware ; les
abonnements et les factures vivent à côté. Cette séparation évite qu'un
décalage de facturation coupe l'accès d'un lycée en pleine journée de cours. Un
établissement suspendu conserve base, fichiers et historique : le jour où le
client règle, une réactivation suffit.

La suppression, elle, efface la base et les fichiers. Elle exige de **retaper
le code** en clair, en ligne de commande comme dans le portail : sur un serveur
où cohabitent les données de tous les clients, la seule protection qui tienne
contre un mauvais copier-coller est de devoir réécrire le nom de la victime.

## Tâches planifiées et files

Une seule entrée cron sur le serveur ; `tenants:run` rejoue la commande dans
chaque établissement actif, et l'échec d'un établissement ne prive pas les
autres de leur traitement. Pour les jobs mis en file — la file est partagée et
le worker n'a aucun contexte au moment du `handle()` — le trait
[`ExecuteDansLEtablissement`](api/app/Tenancy/Concerns/ExecuteDansLEtablissement.php)
mémorise le code à la construction et rebascule avant exécution.

## Annuaire téléphone → établissement

Un enseignant qui a oublié son code n'a que son téléphone. L'index central
[`annuaire_entrees`](api/database/migrations/central/2026_10_10_000040_create_annuaire_entrees_table.php)
répond à « ce numéro appartient à quel établissement ? » sans permettre de
reconstituer l'annuaire des clients : seul un **HMAC du numéro normalisé** y
est stocké, jamais le numéro ni le nom. La route publique est plafonnée — sans
quoi elle permettrait de tester des numéros en masse pour savoir qui travaille
où.

L'index est alimenté par un observateur sur `Enseignant`, dont les échecs sont
journalisés mais jamais propagés : l'annuaire est un confort de connexion, la
fiche de personnel est la donnée métier. En cas de conflit, c'est la fiche qui
gagne.

## Chiffres consolidés

C'est le prix de l'isolation par base : un chiffre global demande une requête
par établissement. Le tableau de bord éditeur boucle donc, avec un cache court
(5 min — aucun de ces chiffres n'a besoin d'être à la seconde), et renvoie
`injoignable: true` pour un établissement dont la base ne répond pas plutôt que
de faire échouer tout le tableau. Afficher `0` laisserait croire à un
établissement sans activité.

## Tests

[`Tests\TestCase`](api/tests/TestCase.php) place chaque test dans le contexte
d'un établissement `TEST` : base centrale en mémoire, base locataire en fichier
SQLite, en-tête `X-Tenant` par défaut sur toutes les requêtes. Les tests métier
héritent du contexte sans une ligne de code, et un test qui oublierait
l'en-tête échouerait en 400 — exactement le comportement de production.

Deux pièges rencontrés, documentés dans le fichier :

- les migrations locataires sont déclarées au migrateur
  (`$this->app->make('migrator')->path(...)`) et non via `migrateFreshUsing()` :
  cette méthode vient d'un trait, et **en PHP une méthode de trait prime sur la
  méthode héritée d'une classe parente** — la redéfinir dans `TestCase`
  n'aurait aucun effet ;
- `parent::tearDown()` doit être appelé **avant** de refermer le contexte,
  sinon la purge de la connexion annule la transaction de `RefreshDatabase` et
  les données du test survivent au test suivant.

Les tests partagent un fichier locataire par processus : `--parallel`
demanderait un fichier par jeton.

## Ce qui reste à décider

- **Sous-domaines par établissement** : le code est prêt
  (`AUDITRON_TENANT_SUBDOMAIN`), mais l'architecture retenue est l'URL unique.
- **Catalogue public** : publier la liste de ses clients est un choix
  commercial ; `AUDITRON_CATALOGUE_PUBLIC=false` rend la saisie du code
  obligatoire.
- **Montée en charge** : au-delà de quelques centaines d'établissements sur un
  même serveur MySQL, le nombre de bases devient un sujet d'exploitation
  (sauvegardes, `max_connections`). Le point de bascule n'est pas proche, mais
  il existe.
