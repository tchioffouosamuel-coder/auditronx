# Identité visuelle — Auditron X

Le logo d'origine (`mobile/assets/logo.png`) n'existait qu'en **186 × 186 px**, soit
1,6 cm à 300 dpi : inutilisable en impression. Il a été **revectorisé** ; les fichiers
de ce dossier sont désormais la référence.

## Fichiers

| Fichier | Usage |
|---|---|
| [`auditron-x-logo.svg`](auditron-x-logo.svg) | **Master vectoriel couleur.** À donner à l'imprimeur, au graphiste, au fabricant de roll-up. Taille illimitée. |
| [`auditron-x-logo-mono.svg`](auditron-x-logo-mono.svg) | Une seule encre (vert `#0A6844`) : tampon, gravure, sérigraphie, fax, photocopie. |
| [`auditron-x-logo-blanc.svg`](auditron-x-logo-blanc.svg) | Blanc avec réserve centrale, pour fond foncé ou photo. |
| [`png/`](png) | Exports matriciels à fond transparent : 512, 1024, 2048 et 4096 px. |
| [`build_logo.py`](build_logo.py) | Script de génération. Toute modification se fait ici, puis `python brand/build_logo.py`. |

## Quelle taille pour quel support

| Support | Fichier |
|---|---|
| Roll-up, bâche, affiche, kakémono | `auditron-x-logo.svg` (**jamais un PNG**) |
| Carte de visite, flyer, en-tête de document | `auditron-x-logo.svg` |
| Site web, signature e-mail, réseaux sociaux | `png/auditron-x-logo-512.png` ou le SVG |
| Icône d'application, favicon | `png/auditron-x-logo-512.png` |
| Dépannage si le prestataire refuse le SVG | `png/auditron-x-logo-4096.png` → 34 cm à 300 dpi |

## Couleurs

| Rôle | Hex | RVB |
|---|---|---|
| Vert circuit (clair) | `#31976E` | 49, 151, 110 |
| Vert circuit (médian) | `#1E8259` | 30, 130, 89 |
| **Vert circuit (foncé — couleur de marque)** | `#0A6844` | 10, 104, 68 |
| Vert gravure | `#00482F` | 0, 72, 47 |
| Or contact (clair) | `#FFE391` | 255, 227, 145 |
| Or contact (foncé) | `#D9A94E` | 217, 169, 78 |
| Encre monogramme | `#121113` | 18, 17, 19 |

Pour une impression offset ou sérigraphie, faire convertir le vert `#0A6844` en
Pantone par l'imprimeur (il n'y a pas d'équivalent Pantone exact).

## Règles d'usage

- **Zone de protection** : laisser autour du logo une marge vide au moins égale à la
  longueur d'une patte métallique (soit 8 % de la largeur du logo).
- **Taille minimale** : 15 mm en impression, 48 px à l'écran. En dessous, les pattes
  et les plages dorées se referment — utiliser la variante `mono`.
- Ne pas déformer, ne pas faire pivoter, ne pas recolorer en dehors des trois
  variantes fournies, ne pas ajouter d'ombre portée supplémentaire.
- Sur fond photo ou foncé, utiliser `auditron-x-logo-blanc.svg`.

## Régénérer

```bash
pip install resvg-py          # seulement pour les exports PNG
python brand/build_logo.py
```

Le script régénère les trois SVG et tous les PNG. La géométrie (nombre de broches,
rayons, palettes) est paramétrée en haut du fichier.
