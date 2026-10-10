#!/usr/bin/env bash
#
# Reprise des instances Auditron existantes comme locataires de la plateforme.
#
# Les bases MySQL de production ne sont ni exportées ni réécrites : elles
# DEVIENNENT les bases des établissements. Ce script ne fait que les déclarer
# dans la base centrale, avec leurs identifiants, puis met leurs schémas à
# niveau.
#
# Les mots de passe ne figurent PAS dans ce fichier : ils sont lus dans
# l'environnement, à fournir au lancement. Rien de secret n'est donc versionné,
# et rien n'apparaît dans l'historique du shell si vous utilisez la forme
# `read -s` ci-dessous.
#
# Usage, depuis saas/api :
#
#     read -rsp 'Mot de passe LTM  : ' MDP_LTM  && echo && export MDP_LTM
#     read -rsp 'Mot de passe LCM  : ' MDP_LCM  && echo && export MDP_LCM
#     read -rsp 'Mot de passe LBM  : ' MDP_LBM  && echo && export MDP_LBM
#     read -rsp 'Mot de passe LBGN : ' MDP_LBGN && echo && export MDP_LBGN
#     read -rsp 'Mot de passe TEST : ' MDP_TEST && echo && export MDP_TEST
#     bash ../scripts/reprise-production.sh
#
# Prérequis dans le .env de l'API :
#   - DB_CENTRAL_DATABASE / DB_USERNAME / DB_PASSWORD : la base centrale, déjà
#     migrée (`php artisan central:migrate --seed`) ;
#   - AUDITRON_CREATE_DATABASE=false : l'utilisateur MySQL de l'hébergement
#     mutualisé n'a pas le droit CREATE DATABASE, et aucune base n'est à créer
#     ici de toute façon ;
#   - AUDITRON_ANNUAIRE_SALT : fixé une fois pour toutes AVANT cette reprise.

set -u -o pipefail

cd "$(dirname "$0")/../api" || exit 1

# --- Garde-fous --------------------------------------------------------------

if [ ! -f .env ]; then
    echo "✗ Aucun .env dans saas/api : copier .env.example et le renseigner." >&2
    exit 1
fi

for variable in MDP_LTM MDP_LCM MDP_LBM MDP_LBGN MDP_TEST; do
    if [ -z "${!variable:-}" ]; then
        echo "✗ Variable $variable absente de l'environnement." >&2
        echo "  Voir l'en-tête de ce script pour la fournir sans l'écrire en clair." >&2
        exit 1
    fi
done

# La base centrale doit répondre et être migrée : sans elle, chaque commande
# échouerait l'une après l'autre avec un message peu parlant.
if ! php artisan tinker --execute='App\Models\Central\Etablissement::count();' > /dev/null 2>&1; then
    echo "✗ Base centrale injoignable ou non migrée." >&2
    echo "  Lancer d'abord : php artisan central:migrate --seed" >&2
    exit 1
fi

echo "→ Base centrale joignable."
echo

# --- Déclaration des établissements ------------------------------------------

# declarer <CODE> <NOM> <PLAN> <VILLE> <BASE> <UTILISATEUR> <MOT_DE_PASSE>
#
# `--base-existante`  : la base est déjà là, ne pas la créer ;
# `--sans-amorcage`   : accréditations et comptes existent déjà dedans ;
# `--db-user/password`: hébergement mutualisé, un utilisateur par base.
declarer() {
    local code="$1" nom="$2" plan="$3" ville="$4" base="$5" utilisateur="$6" motdepasse="$7"

    echo "→ $code — $nom"

    if php artisan etablissement:create "$code" "$nom" \
        --plan="$plan" \
        --ville="$ville" \
        --base-existante \
        --sans-amorcage \
        --db="$base" \
        --db-user="$utilisateur" \
        --db-password="$motdepasse"
    then
        echo "  ✓ déclaré"
    else
        # Cas courant d'une reprise rejouée : l'établissement existe déjà. Le
        # script continue, les autres n'ont pas à en souffrir.
        echo "  ✗ échec (déjà déclaré ? voir le message ci-dessus)" >&2
        ECHECS="${ECHECS}$code "
    fi

    echo
}

ECHECS=""

declarer LTM  "Lycée Technique de Meiganga"  standard "Meiganga"   u133979320_ltm  u133979320_ltm  "$MDP_LTM"
declarer LCM  "Lycée Classique de Meiganga"  standard "Meiganga"   u133979320_lcm  u133979320_lcm  "$MDP_LCM"
declarer LBM  "Lycée Bilingue de Meiganga"   standard "Meiganga"   u133979320_lbm  u133979320_lbm  "$MDP_LBM"
declarer LBGN "Lycée Bilingue de Ngaoundal"  standard "Ngaoundal"  u133979320_lbgn u133979320_lbgn "$MDP_LBGN"

# Environnement d'essai, conservé comme locataire pour pouvoir répéter une
# manipulation en conditions réelles sans toucher à un vrai établissement.
declarer TEST "Lycée d'Auditron"             essai    ""           u133979320_test u133979320_test "$MDP_TEST"

# --- Mise à niveau des schémas -----------------------------------------------

# C'est ici que LCM et LBM récupèrent les migrations qu'elles n'ont jamais
# reçues du temps des copies : journal d'audit, OTA des bornes, horaires
# administratifs.
echo "→ Mise à niveau des schémas de tous les établissements…"
php artisan tenants:migrate
echo

# --- Vérification ------------------------------------------------------------

# Lecture réelle dans chaque base : c'est le seul contrôle qui prouve que les
# identifiants passés plus haut fonctionnent.
echo "→ Contrôle : effectifs lus dans chaque base."
php artisan tinker --execute='
        foreach (App\Models\Central\Etablissement::orderBy("code")->get() as $e) {
            try {
                $chiffres = app(App\Tenancy\TenantManager::class)->execute($e, fn () => [
                    App\Models\Enseignant::count(),
                    App\Models\Presence::count(),
                ]);
                printf("  %-6s %4d agents, %6d pointages\n", $e->code, $chiffres[0], $chiffres[1]);
            } catch (Throwable $ex) {
                printf("  %-6s INJOIGNABLE : %s\n", $e->code, $ex->getMessage());
            }
        }
    '

echo
if [ -n "$ECHECS" ]; then
    echo "⚠ Établissements non déclarés : $ECHECS" >&2
    echo "  Les reprendre un par un, ou utiliser le portail éditeur (/plateforme)." >&2
    exit 1
fi

echo "✓ Reprise terminée. Étapes suivantes : fichiers, annuaire, puis portail web."
echo "  Voir saas/MIGRATION.md (sections « Fichiers » et « Annuaire »)."
