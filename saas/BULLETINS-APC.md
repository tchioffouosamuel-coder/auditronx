# Bulletins de compétences (modèle APC) — état et suite du travail

Écrit pour la personne qui reprend le chantier. Décrit ce qui est en place,
pourquoi c'est ainsi, et ce qu'il reste à faire.

Le modèle de référence est la formation des censeurs industriels sur
l'implémentation des bulletins de compétences APC au second cycle de
l'enseignement secondaire technique et professionnel industriel. Quatre
bulletins y servent de cas pratiques — deux trimestriels et deux annuels, une
élève admise et un élève en échec. **Ils sont la spécification.** Le jour où un
calcul change, c'est contre eux qu'il faut le vérifier.

---

## 1. Où en est le chantier

| Lot | Contenu | État |
|---|---|---|
| 0 | Barème APC et service de calcul | **fait** — 16 tests |
| 1 | Départements, spécialités, modules, import du classeur | **fait** — 22 tests |
| 2 | Maquette de classe | **fait** — 18 tests |
| 3 | Élèves et inscriptions | **fait** — 25 tests, dont 11 sur la liste de classe |
| 4 | Saisie des notes | **fait** — 22 tests |
| 5 | Calcul des bulletins et conseil de classe | **fait** — 16 tests |
| 6 | Impression PDF par classe | **fait** — 16 tests, dont les 4 bulletins de référence |

Suite complète au moment d'écrire : **381 tests, 378 passent**. Les trois
échecs sont antérieurs au chantier (voir §8).

### La chaîne visée

```
Départements ─▶ Spécialités ─▶ Modules (import XLSX) ─▶ Maquette de classe
                                                             │
                 Élèves ─▶ Inscriptions ────────────────────┤
                                                             ▼
                                            Saisie des notes (grille / XLSX)
                                                             │
                                             Conseil de classe (décisions)
                                                             ▼
                                    Bulletins PDF — un fichier par classe
```

Les sept lots sont faits, et le critère de fin est atteint : les quatre
bulletins des cas pratiques ressortent à l'identique (§6).

---

## 2. Ce qui existe déjà

### Lot 0 — le barème

[`app/Services/BaremeApc.php`](api/app/Services/BaremeApc.php) — tout ce qui
transforme des notes en décisions, et rien ailleurs.

| Méthode | Règle |
|---|---|
| `noteFinaleTrimestre()` | UEA (/60) + épreuve intégratrice (/40) |
| `noteFinaleAnnuelle()` | moyenne des trimestres **programmés** |
| `moyenneGenerale()` | `Σ(note × crédit) / Σ crédit` |
| `synthese()` | moyenne, crédits, VH, compétences non validées, mention, validation |
| `mention()` · `sanction()` · `appreciation()` | A→E · `S`/`E` · « Non acquise – à reprendre » |
| `decisionTrimestre()` · `decisionAnnuelle()` | Succès/Remédiation/Échec · Admis/Sous réserve/Redoublement |
| `positionGroupe()` | tiercile de rang |

Réglages par établissement dans `parametres`, préfixe `apc.`, écran
Configuration → onglet « Barème des bulletins ».

Trois règles à connaître avant d'écrire la suite :

- **L'arrondi au dixième est appliqué avant tout le reste.** Mention, sanction
  et moyenne générale partent de la note arrondie. Sans cela un bulletin n'est
  pas refaisable à la main par un parent.
- **Les bornes de mention sont des bornes inférieures** (`A≥90, B≥85…`). Le
  tableau officiel écrit des intervalles fermés qui laissent 84,5 sans mention.
- **Une note incomplète ne vaut pas zéro.** `noteFinaleTrimestre()` rend `null`
  si l'une des deux parts manque.

### Lot 1 — le référentiel

| Table | Clé naturelle |
|---|---|
| `departements` | `code` |
| `specialites` | `code`, `departement_id` nullable |
| `modules` | `(specialite_id, niveau, code)` |

`disciplines` **n'est pas touchée** : elle porte l'emploi du temps, le cahier
de texte, les progressions et l'assiduité. `modules.discipline_id` est un pont
facultatif, nullable, pour le jour où relier un module à son cours servira.

[`app/Services/ReferentielApc.php`](api/app/Services/ReferentielApc.php) lit le
classeur d'organisation pédagogique dans sa disposition d'origine : un onglet
par spécialité, une colonne par bloc, le volume horaire en regard. `lire()`
n'écrit rien, `appliquer()` écrit — c'est ce qui permet le bouton « Analyser »
avant « Importer ».

Ce qu'il refuse de deviner, et qu'il nomme ligne par ligne : deux blocs sur une
même ligne (VH laissé vide sur les deux), code déjà vu dans l'onglet (rejeté en
citant la première ligne), module sans code (code fabriqué et signalé), écart
entre les heures lues et l'onglet « Contrôle ».

Le fichier réel est en fixture :
`tests/Fixtures/referentiel-apc-op.xlsx`.

**L'import n'est jamais destructif** : un module absent du classeur n'est pas
supprimé, un volume horaire connu n'est pas effacé par une ligne ambiguë.

### Lot 2 — la maquette

`classe_module` : ce que *cette* classe suit cette année. C'est elle — et non
`modules` — que parcourt un bulletin.

Crédits et heures sont **recopiés** du référentiel, pas référencés : une
correction du référentiel en janvier ne doit pas réécrire les bulletins du
premier trimestre.

`trimestres` (json) porte le tiret du bulletin annuel :

| Valeur | Sens | Au bulletin |
|---|---|---|
| `[1,2,3]` | programmé partout | notes aux trois trimestres |
| `[2,3]` | ouvert au 2ᵉ trimestre | **tiret** au T1, hors dénominateur de la moyenne annuelle |
| `[]` | non programmé cette année | imprimé, **crédit entre parenthèses**, hors totaux |
| `null` | jamais renseigné | vaut **tous** les trimestres, jamais « aucun » |

Retirer un module de la maquette et le laisser non programmé sont deux gestes
différents : le premier le fait disparaître du bulletin, le second l'y laisse.

---

## 3. Lot 3 — Élèves et inscriptions *(fait)*

### Tables

| Table | Clé naturelle |
|---|---|
| `eleves` | `matricule` (unique) |
| `inscriptions` | `(eleve_id, annee_scolaire)` |

**L'inscription est l'unité du chantier, pas l'élève.** Les notes et les
bulletins s'y accrocheront, jamais à `eleve_id` : c'est ce qui permet à un
redoublant d'avoir deux années côte à côte sans que la seconde écrase la
première. Changer de classe en cours d'année *modifie* l'inscription — il n'y
en a pas deux, puisqu'il n'y a qu'un bulletin par trimestre.

`matricule` est unique, contrairement à celui du personnel dont la contrainte a
dû être levée : c'est la clé de l'import et il s'imprime sur le bulletin. Deux
élèves qui le partagent, ce sont deux bulletins qu'on ne sait plus attribuer.

`actif` distingue l'élève parti en cours d'année de celui qui n'a jamais été
inscrit : il garde ses bulletins mais sort des listes de saisie.

### L'année scolaire

[`app/Services/AnneeScolaire.php`](api/app/Services/AnneeScolaire.php) —
bascule au 1er septembre, format `2025/2026`, celui de
`programmes.annee_scolaire`. La règle vivait dans une méthode privée de
`CoursEnseignantController`, qui l'appelle désormais depuis ici : trois copies
d'une règle de calendrier finissent toujours par diverger d'un mois.

`normalise()` accepte `2025-2026`, `2025 2026` et `2025/26`. Une valeur
illisible est rendue telle quelle plutôt qu'inventée.

Le bulletin imprime l'année au tiret (« Année Scolaire : 2025-2026 ») : c'est
une question de présentation, traitée à l'impression, pour ne pas couper les
nouvelles données des anciennes.

### API

```
GET|POST|PUT|DELETE  /api/eleves              identité
GET                  /api/eleves/annees       années ayant des inscriptions
GET                  /api/classes/{classe}/eleves?annee=    liste de classe
GET|POST|PUT|DELETE  /api/inscriptions
```

**L'effectif de `/classes/{classe}/eleves` est compté sur les inscriptions
actives**, pas lu dans `classes.effectif` — une valeur saisie à la main, qui se
périme au premier départ et fausserait aussi le rang. C'est cette route que
consommeront la grille de saisie et l'en-tête du bulletin.

### Import

Au format de la **liste de classe de l'administration** :
[`ListeElevesController`](api/app/Http/Controllers/Api/ListeElevesController.php)
et [`App\Support\ListeEleves`](api/app/Support/ListeEleves.php), qui lit et
écrit le classeur. Un contrôleur à part, comme le référentiel : la classe et
l'année ne sont pas des colonnes, elles se lisent une fois par onglet dans le
titre.

- un onglet par classe ; ligne 1, titre fusionné
  « LISTE DES ÉLÈVES — 2nde F4-BA — 2026-2027 » ;
- ligne 3, les en-têtes : N°, NOMS ET PRÉNOMS, MATRICULE NATIONAL, MATRICULE
  INTERNE, DATE DE NAISSANCE, LIEU DE NAISSANCE, TÉLÉPHONE, SEXE, STATUT ;
- un élève par ligne à partir de la 4.

Correspondances :

- la clé est le **matricule interne** (`eleves.matricule`) ; le matricule
  national manque sur une bonne part des listes et ne peut pas l'être ;
- NOMS ET PRÉNOMS va dans `nom`, espaces réduits, et efface `prenoms`, pour que
  le nom ne s'imprime pas deux fois ;
- STATUT N (nouveau) → `regulier`, R → `redoublant` ; une cellule vide vaut N ;
- `matricule_national` et `telephone` ont été ajoutés à `eleves` par la
  migration `2026_11_28_090110`.

La classe du titre se cherche par code puis par nom, sans casse ni accents. Une
classe inconnue **refuse l'onglet entier**, avec une erreur sur la ligne du
titre : inscrire quarante élèves nulle part sur une faute de frappe ne se
verrait pas. Un titre « SANS CLASSE » enregistre l'identité seule, puisque les
listes arrivent parfois avant les affectations. Sans année dans le titre,
l'année en cours s'applique.

La lecture reconnaît les colonnes à leur intitulé, pas à leur position (accents,
casse, ordre, ligne d'en-têtes décalée). Un onglet sans en-têtes reconnus est
ignoré ; un fichier qui n'en a aucun est refusé en 422, ancien modèle à plat
compris.

Les sorties :

- le **modèle** donne un onglet vierge par classe, titre rempli ;
- l'**export** (`?annee=`, l'année en cours par défaut) donne un onglet par
  classe, plus « SANS CLASSE », et se réimporte tel quel ;
- l'import est réservé au back-office.

`Classeur::date()` lit le **numéro de série d'Excel** (39521 → 14/03/2008)
aussi bien que `JJ/MM/AAAA` : une colonne formatée en date arrive ici en
nombre, et la traiter comme une chaîne donnerait une naissance en l'an 39521.

### Écran

`/eleves`, deux onglets — « Élèves » (identité, filtres classe/année, import)
et « Inscriptions ».

## 4. Lot 4 — Saisie des notes *(fait)*

### Table

`notes_modules`, clé naturelle `(inscription_id, classe_module_id, trimestre)`.

Deux chiffres saisis — `moyenne_uea` et `note_integratrice` — et leur somme
`note_finale`.

**`note_finale` est stockée, et recalculée dans le modèle à chaque
enregistrement** (`NoteModule::booted()`), pas dans le contrôleur : un
appelant qui oublierait de le faire — un import, une commande, un futur écran
— laisserait en base une note finale en désaccord avec ses composantes,
c'est-à-dire un bulletin faux que rien ne signale.

La note s'accroche à `classe_module_id`, pas au module du référentiel : c'est
la ligne de maquette qui porte le crédit et les trimestres de programmation.

`verrouillee_at` est prêt pour le lot 5 : le refus d'écriture est **déjà
implémenté** côté API et côté classeur, il ne reste qu'à poser l'horodatage à
la validation d'un bulletin.

### API

```
GET  /api/notes?classe_id=&trimestre=&annee=                    avancement de la classe
GET  /api/notes?…&classe_module_id=                             grille d'un module
PUT  /api/notes/bulk                                            enregistrement en lot
GET  /api/notes/avancement?classe_id=&trimestre=&annee=
GET  /api/notes/classeur/export?classe_id=&trimestre=&annee=    XLSX pré-rempli
POST /api/notes/classeur/import                                 réimport (apercu=1 possible)
```

Quatre refus volontaires, chacun avec son test :

- **module non programmé au trimestre** — une note y serait ignorée par la
  moyenne annuelle sans que personne ne le voie ;
- **module d'une autre classe** ;
- **élève hors de la classe pour l'année** ;
- **note verrouillée** par un bulletin validé.

Une saisie dont les deux champs sont vidés est **supprimée**, pas mise à
`null` : sinon elle resterait indistinguable d'une case jamais remplie dans
l'avancement.

`avancement` ne compte que les notes **complètes** et rend `prete: true` quand
tous les modules programmés sont remplis pour tout l'effectif actif. Sans ce
décompte, personne ne découvre qu'il manque une colonne avant le conseil.

### Le classeur hors ligne

Un onglet par module programmé, les élèves pré-remplis, le barème écrit dans
l'en-tête des colonnes (`uea_60`, `integratrice_40`). Rapprochement par **code
de module** (nom d'onglet) et **matricule** : ni l'ordre des onglets ni celui
des lignes ne comptent. La virgule décimale est acceptée.

Un test vérifie qu'exporter puis réimporter sans rien changer ne modifie rien.

### Écran

`/notes` — liste d'avancement, puis une grille par module. Note finale et
mention calculées **en direct dans le navigateur**, à partir des bornes que
l'API envoie avec la grille : afficher la mention du serveur la laisserait
périmée dès la première correction.

### Outil partagé apparu ici

[`app/Support/Classeur.php`](api/app/Support/Classeur.php) — `nomsOnglets()`
et `nomOngletUnique()`. `Excel::toArray()` rend les onglets sans leur nom,
alors que trois imports s'en servent comme d'une donnée. La lecture existait en
copies privées dans `ReferentielApcController` et `SpreadsheetController` ;
les deux appellent désormais le service.

## 5. Lot 5 — Calcul des bulletins et conseil de classe *(fait)*

### Tables

`bulletins` — un par inscription et par **période** : `periode` vaut `1`,
`2`, `3` ou `annuel`. Une seule colonne non nulle porte la clé
`(inscription_id, periode, version)` : un index unique sur un `trimestre`
nullable ne protège rien, deux `NULL` ne sont jamais égaux. `type` et
`trimestre` restent à côté pour la lecture.

`bulletin_lignes` — l'instantané : code, intitulé, bloc, crédit, heures,
`trimestres` (programmation recopiée), `notes_trimestres` (json),
`moyenne_uea` / `note_integratrice` (trimestre), `note_finale`, mention,
sanction, appréciation, signataire, `hors_totaux`, `note_manquante`.

Écarts au plan initial, et pourquoi :

- `notes_trimestres` en json plutôt que `note_t1..t3` : le nombre de
  trimestres est un réglage (`apc.nombre_trimestres`, jusqu'à 6).
- `decision_proposee` (barème) **et** `decision_conseil` (choix du conseil,
  avec `decision_conseil_par` / `_at`) au lieu d'une seule `decision`. Nul =
  le conseil suit la proposition. Le PV pourra montrer où le conseil s'est
  écarté du calcul.
- `inscription_id` et `classe_id` en `restrictOnDelete` : supprimer un élève
  ou une classe qui a des bulletins échoue au lieu de les emporter.

### Service

[`CalculBulletin`](api/app/Services/CalculBulletin.php) — une passe pour la
classe entière, appelle `BaremeApc::synthese()` par élève puis classe.

| Cas | Au bulletin |
|---|---|
| module non programmé à la période | tiret, `hors_totaux` |
| module programmé, note absente | `note_manquante` ; le bulletin n'est **ni classé, ni décidé, ni validable** |
| trimestre programmé sans note (annuel) | ligne annuelle incomplète — pas de moyenne de 2 trimestres sur 3 |
| module programmé sans crédit | **génération refusée** pour toute la classe |

La moyenne des seuls modules notés serait plus flatteuse que la vraie. Une
absence à l'épreuve se saisit **0** : le zéro est un geste, la case vide un
oubli.

Rang : sur la moyenne **arrondie**, ex aequo partagés (1, 2, 2, 4).
Position dans le groupe et moyennes de classe : sur les seuls élèves
classés. `effectif` reste l'effectif actif complet.

**Empreinte** (`sha256`) : notes, maquette, liste de classe et barème au
moment du calcul. La validation la compare à l'état présent. Une note
corrigée après le calcul rend le conseil invalidable tant qu'on n'a pas
recalculé.

### Versions et verrous

[`ConseilClasse`](api/app/Services/ConseilClasse.php).

- **Brouillon** : réécrit à chaque recalcul. La décision du conseil et
  l'observation sont conservées.
- **Valider** : toute la classe à la fois, refusé s'il reste un obstacle
  (bulletin incomplet, élève sans bulletin, données changées). Pose
  `verrouillee_at` sur les notes qui ont servi.
- **Rouvrir** : motif obligatoire. Les bulletins signés sont marqués
  `remplace_at` / `remplace_par` / `motif_reouverture` et **jamais
  effacés**. Le recalcul suivant crée la version n+1, qui reprend
  décision et observation.
- Un trimestre ne se rouvre pas tant que l'annuel est validé : l'annuel
  repose sur ses notes.
- Les verrous sont **recalculés en entier** (`synchroniserVerrous`) : une
  note de T2 est tenue par le bulletin de T2 *et* par l'annuel, et rouvrir
  l'un ne doit pas libérer ce que l'autre retient.

Validation et réouverture sont journalisées (`conseil.valide`,
`conseil.rouvert`). `Bulletin` est dans les modèles audités : chaque
changement de décision garde son avant/après.

### API

```
GET  /api/conseil?classe_id=&periode=1|2|3|annuel&annee=   état, élèves par rang, obstacles
POST /api/conseil/generer                                  (re)calcule les brouillons
POST /api/conseil/valider                                  fige, verrouille les notes
POST /api/conseil/rouvrir        {motif}                   garde la version signée
GET  /api/bulletins/{bulletin}                             détail, lignes comprises
PUT  /api/bulletins/{bulletin}   {decision?, observation?} brouillon seulement
```

Lecture ouverte, écriture backoffice.

### Écran

`/conseil` — classe, période, année ; élèves triés par rang, décision
proposée modifiable (la proposition est marquée), observation par élève,
détail des lignes, boutons Calculer / Valider / Rouvrir. Les obstacles à la
validation sont listés en clair au-dessus du tableau.

## 6. Lot 6 — Impression *(fait)*

### Principe

**La vue ne recalcule rien.** [`ImpressionBulletin`](api/app/Services/ImpressionBulletin.php)
lit l'instantané du lot 5 et ne fait que mettre en forme : virgule décimale
(une décimale toujours, `87,0`), regroupement par bloc, tiret, parenthèses,
initiales du signataire (`MBARGA Jean Paul` → `M.J.P.`), année au tiret.
Seules l'identité de l'élève et la spécialité de la classe sont lues en
direct, pour qu'une faute corrigée sur un nom le soit aussi à la
réimpression.

**Le barème est recopié sur le bulletin** (`bulletins.bareme`, migration
`090100`). La légende des mentions et le seuil imprimés sont ceux du calcul.
Sinon, une réimpression faite après un changement de bornes afficherait un
« B » à 85,0 sous une légende « B ≥ 86 ».

| Au bulletin | Lu dans |
|---|---|
| tiret d'un trimestre | `bulletin_lignes.trimestres` |
| crédit et VH entre parenthèses | `hors_totaux` |
| case vide (brouillon seulement), ligne surlignée | `note_manquante` |
| note en rouge | `sanction = E` |
| case cochée de la décision | `decision_conseil ?? decision_proposee` |

### Vues

- `pdf/bulletins.blade.php` est l'enveloppe commune : un élève par page,
  dans l'ordre de la liste d'appel, avec filigrane, en-tête, identité,
  tableau et synthèse.
- `pdf/bulletin-trimestre.blade.php` (9 colonnes) et
  `pdf/bulletin-annuel.blade.php` (une colonne par trimestre) ne contiennent
  que le tableau. Une classe est toujours d'un seul type.
- `pdf/partials/bulletin-identite` et `pdf/partials/bulletin-synthese` sont
  partagés : en-tête de l'élève, puis sections II (synthèse) et III
  (décision du conseil), avec les cases `[X]` / `[ ]` du canevas.
- `pdf/pv-conseil.blade.php` est en A4 paysage : classe par rang, décision
  proposée et décision retenue (`*` si le conseil s'est écarté du barème),
  décompte par décision, taux de « S » et moyenne par module.

Police **DejaVu Sans** : dompdf remplace Arial par Helvetica, à qui
manquent des glyphes (tiret long, apostrophe typographique).

**Rendu vérifié à l'œil.** Le bulletin annuel de 13 modules tient sur une
page A4 portrait, en police 8,4 px.
Le cas « serré » redouté n'en est pas un.

Un document qui n'est pas la version signée porte un filigrane :
**BROUILLON** (le conseil relit sur papier avant de valider) ou **VERSION
REMPLACÉE** (réimpression d'une version rouverte).

### API

```
GET /api/bulletins/{bulletin}/pdf                         un élève
GET /api/bulletins/classe/{classe}/pdf?periode=&annee=    toute la classe
GET /api/bulletins/classe/{classe}/pv?periode=&annee=     PV du conseil
GET /api/bulletins/export-zip?periode=&annee=             un dossier par classe (backoffice)
```

`periode` vaut `1|2|3|annuel`, comme pour le conseil. Le trait
`LitPeriodeConseil` est partagé entre les deux contrôleurs.
`{bulletin}` est limité aux nombres (`whereNumber`), pour que « classe » ou
« export-zip » ne soient jamais pris pour un identifiant.

Le ZIP ne prend que les bulletins **validés**. Il contient, par classe, un
dossier avec le PDF des bulletins et le PV.

### Écran

Sur `/conseil` : boutons « Bulletins (PDF) », « PV du conseil », « Archive
établissement (ZIP) », et un bouton « PDF » par élève.

### Critère de fin — atteint

La référence est la présentation de la formation des censeurs industriels
(canevas trimestriel et annuel, puis quatre cas pratiques : NGONO Aline
Carine admise, TCHAMBA Kevin Arnaud en échec, 1re année TPV, T3 et
annuel). [`BulletinsDeReferenceTest`](api/tests/Feature/BulletinsDeReferenceTest.php)
les ressaisit (même maquette, mêmes notes, même programmation par
trimestre). Il compare **chaque case** de chaque ligne, puis le pied du
tableau : 84,4 · 67,2 · 84,1 · 66,9 ; 60 et 97 crédits ; 1410 h ; 4 et 7
compétences non validées. Le moteur n'a pas eu à changer. Seule la mise
en page a été alignée sur le canevas :

- notes sans décimale inutile (`87`, `86,5`) ;
- trimestriel : seuls les modules **programmés au trimestre** sont imprimés
  (le modèle totalise 60 crédits au T3, l'année en compte 97) ;
- colonnes du canevas, code dans la case du module, pas de VH au trimestre ;
- les quatre blocs toujours imprimés, « sans objet » s'ils sont vides ;
- ligne « Moyenne Générale des notes pondérées » (moyenne, crédits, VH,
  mention, décision, compétences validées) et ligne « Seuil de réussite » ;
- sections I / II / III, cases `[X]`, légende en intervalles ;
- module non programmé de l'année : crédit `(20)`, VH `300`, « Non
  programmé ».

Les moyennes de classe du modèle (effectif 28) ne sont pas reconstituées.

### Points ouverts

- **Décision annuelle.** Le pied du bulletin annuel du modèle porte
  « SUCCÈS » / « ÉCHEC ». Le barème (lot 0) nomme la décision annuelle
  Admis / Validation sous réserve / Redoublement. La section III de l'annuel
  est coupée dans la présentation : **à trancher** avec un censeur.
- **Colonne SIGN.** Les captures du modèle sont rognées à droite après
  « APPRÉCIATION ». La colonne de signature (initiales de l'enseignant,
  lot 2) est gardée en dernière position, **à confirmer**.
- **Rang** : le modèle ne l'imprime pas sur le bulletin. Il est retiré du
  bulletin et reste au PV.
- **Le ZIP est rendu en synchrone.** Pour une quarantaine de classes, cela
  prend quelques minutes. Au-delà, il faudra un job en file d'attente et un
  lien de téléchargement.
- **Statut `publie`** (remise aux familles) : la constante existe, aucun
  écran ne le pose encore.

---

## 7. Pièges rencontrés — à ne pas redécouvrir

**Le guard Sanctum mémorise l'utilisateur de la première requête d'un test.**
Un second `withToken()` dans le même test ne change pas d'identité : les
requêtes suivantes restent authentifiées comme le premier compte. Un test de
périmètre doit donc ne connaître **qu'une** identité, et poser ses données par
Eloquent plutôt que par une requête authentifiée. Voir
`ReferentielApcTest::test_un_enseignant_lit_le_referentiel_mais_ne_lecrit_pas`.

**Le fichier SQLite locataire est partagé par le processus.** Deux exécutions
de phpunit en parallèle le corrompent (`database disk image is malformed`). En
cas de doute :

```bash
rm -f storage/framework/testing/tenant-test.sqlite
```

**`distinct` sous deux jokers compare toutes les lignes entre elles.** Une
règle `lignes.*.trimestres.*` → `distinct` refuse deux modules programmés au
même trimestre, c'est-à-dire le cas normal. Dédoublonner à l'écriture.

**Laravel singularise « eleves » en « elefe ».** Un `apiResource('eleves', …)`
nomme son paramètre `{elefe}`, qui ne correspond à aucun argument type-hinté :
la liaison de modèle ne se fait pas, sans rien dire. Passer
`->parameters(['eleves' => 'eleve'])`.

**`sortBy` avec un tableau de fermetures ne les enchaîne pas.** Utiliser une
clé composée à largeur fixe (`sprintf('%02d-%04d-%s', …)`), comme dans
`MaquetteClasse::lignes()`.

**Le barème est mémorisé par base, dans une propriété statique.** La
transaction d'un test ne l'annule pas : un test qui appelle
`BaremeApc::definir()` doit appeler `BaremeApc::oublier()` dans son
`tearDown`, sinon les tests suivants calculent avec ses bornes.

**Voir un PDF de test** : `BULLETIN_PDF_DUMP=<dossier>` dépose les PDF de
`BulletinPdfTest`, puis `pdftoppm -png` permet de les regarder.

**Une règle `string` refuse `"periode": 1` envoyé en JSON.** Un paramètre
qui vaut tantôt un nombre, tantôt `annuel` se valide par `in:` seul.

**Les migrations vont dans `database/migrations/tenant/`** et les modèles ne
déclarent **aucune** connexion : ils suivent la bascule de `TenantManager`.

---

## 8. Dette connue et points ouverts

### Trois tests en échec, antérieurs au chantier

Vérifiés par `git stash` avant le lot 1 — ils échouaient déjà :

- `AttendanceScanTest::test_une_sortie_est_refusee_avant_50_minutes_de_presence`
- `PerimetreDisciplinesTest::test_il_ne_liste_que_son_departement_et_les_matieres_transversales`
- `PerimetreDisciplinesTest::test_la_direction_garde_la_main_sur_tous_les_departements`

### Deux notions de « département » cohabitent

`disciplines.departement` (texte libre) filtre le périmètre des accréditations
— voir `AccessiblesDisciplines`. La table `departements` structure les
spécialités du référentiel APC. **Elles ne se croisent pas aujourd'hui**, mais
un lecteur futur les confondra. À documenter dans `ARCHITECTURE.md`, ou à
unifier si le périmètre des accréditations doit un jour porter sur les
bulletins.

### Libellés de spécialités à compléter

L'import crée les spécialités avec leur **code pour libellé** : un onglet
« F2 » ne dit pas s'il s'agit d'électronique ou d'électrotechnique, et
l'application ne l'invente pas. Les noms longs de `SH`, `F2`, `MVT` et
`F6-MIPE`, ainsi que leur rattachement aux départements, restent à saisir à
l'écran.

### Anomalies du classeur fourni, à corriger à la source

Détectées et signalées par l'import, mais c'est le document qu'il faut
reprendre :

| Onglet | Ligne | Problème |
|---|---|---|
| F2 | 5 | deux modules sur une ligne — à qui vont les 75 h ? |
| F2 | 6 | `C1 · Métier et formation` en double |
| MVT | 12, 17 | `Français` et `EPS` en double |
| MVT, F6 | — | aucun code de module dans tout l'onglet |
| F6 | 4 | `Confection des vêtements classiques (C14)` — reliquat de SH dans une spécialité de chimie |
| F6 | — | 1005 h lues contre 990 h annoncées, **écart de 15 h** |

Le reliquat C14 de F6 **n'est pas détectable automatiquement** : c'est un
doublon exact d'une entrée légitime de SH, et les répétitions entre spécialités
sont normales (`C1` est partout). Sa ligne est signalée pour une autre raison —
elle porte deux blocs. Le repérer reste un acte de relecture humaine.

---

## 9. Vérifier le travail

```bash
cd saas/api && ./vendor/bin/phpunit --no-coverage
```

Les tests du chantier :

```bash
cd saas/api && ./vendor/bin/phpunit --no-coverage --filter 'BaremeApcTest|ReferentielApcTest|MaquetteClasseTest|ElevesEtInscriptionsTest|SaisieNotesTest|ConseilClasseTest|BulletinPdfTest|BulletinsDeReferenceTest'
```

Côté portail :

```bash
cd saas/web && npm run build
```
