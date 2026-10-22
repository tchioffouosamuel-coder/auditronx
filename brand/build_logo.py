#!/usr/bin/env python3
"""Genere le logo Auditron X en vectoriel (SVG) et ses exports PNG haute definition.

    python brand/build_logo.py

Le trace du monogramme AX a ete vectorise depuis l'ancien logo matriciel
(mobile/assets/logo.png, 186x186 px) : ce fichier est desormais la source de verite.

Dependance pour les exports PNG : pip install resvg-py (facultatif, le SVG sort toujours).
"""
from pathlib import Path

OUT = Path(__file__).parent

# --- monogramme AX, vectorise ------------------------------------------------
GLYPHE = (
    "M 95.88,75.75 L 103.00,75.87 L 112.50,91.87 L 113.88,91.25 L 119.88,83.25 "
    "L 128.12,83.25 L 116.75,99.87 L 130.62,123.99 L 122.50,124.12 L 114.38,110.12 "
    "L 111.75,107.12 L 99.00,124.12 L 90.50,124.12 L 108.12,98.75 L 101.50,86.87 "
    "L 99.88,84.62 L 91.62,97.99 L 102.50,98.37 L 99.25,104.75 L 97.88,105.99 "
    "L 88.12,106.25 L 77.75,124.12 L 69.38,124.25 L 89.88,85.49 Z"
)
CADRAGE = "translate(100 100) scale(.93) translate(-100 -100)"

# --- geometrie (repere 200 x 200, marque centree) ----------------------------
N_BROCHES = 8
PAS = 12.3
PLAGE_L, PLAGE_P = 5.4, 8.0      # plage de contact doree : largeur, profondeur
PATTE_L, PATTE_LG = 3.2, 16.0    # patte metallique : largeur, longueur
CORPS = (34.0, 166.0)            # bords du corps vert

# --- palettes ----------------------------------------------------------------
PALETTES = {
    "couleur": dict(
        vert=[("0", "#31976E"), (".45", "#1E8259"), ("1", "#0A6844")],
        or_=[("0", "#FFE391"), ("1", "#D9A94E")],
        patte=[("0", "#6E6D71"), (".5", "#3A3B3C"), ("1", "#5B5A5E")],
        touche=[("0", "#FFFFFF"), (".55", "#EDEDEF"), ("1", "#D2D1D6")],
        encre=[("0", "#2B2A2C"), ("1", "#121113")],
        gravure="#00482F", point="#DCEFE5", relief="#6E6E72", relief_opacite=".6",
        liseres=True,
    ),
    "mono": dict(
        vert=[("0", "#0A6844"), ("1", "#0A6844")],
        or_=[("0", "#0A6844"), ("1", "#0A6844")],
        patte=[("0", "#0A6844"), ("1", "#0A6844")],
        touche=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        encre=[("0", "#0A6844"), ("1", "#0A6844")],
        gravure="#FFFFFF", point="#FFFFFF", relief="#0A6844", relief_opacite="0",
        liseres=False, reserve=False,
    ),
    "blanc": dict(
        vert=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        or_=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        patte=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        touche=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        encre=[("0", "#FFFFFF"), ("1", "#FFFFFF")],
        gravure="#FFFFFF", point="#FFFFFF", relief="#FFFFFF", relief_opacite="0",
        liseres=False, reserve=True,
    ),
}
for _p in PALETTES.values():
    _p.setdefault("reserve", False)


def degrade(nom, arrets, horizontal=False):
    x2, y2 = ("1", "0") if horizontal else ("0", "1")
    stops = "".join('<stop offset="%s" stop-color="%s"/>' % (o, c) for o, c in arrets)
    return '<linearGradient id="%s" x1="0" y1="0" x2="%s" y2="%s">%s</linearGradient>' % (nom, x2, y2, stops)


def broches():
    a, b = CORPS
    pattes, plages = [], []
    for i in range(N_BROCHES):
        c = round(100 + (i - (N_BROCHES - 1) / 2) * PAS, 2)
        pattes += [
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.2" fill="url(#patte-v)"/>' % (c - PATTE_L / 2, a - PATTE_LG, PATTE_L, PATTE_LG + 4),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.2" fill="url(#patte-v)"/>' % (c - PATTE_L / 2, b - 4, PATTE_L, PATTE_LG + 4),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.2" fill="url(#patte-h)"/>' % (a - PATTE_LG, c - PATTE_L / 2, PATTE_LG + 4, PATTE_L),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.2" fill="url(#patte-h)"/>' % (b - 4, c - PATTE_L / 2, PATTE_LG + 4, PATTE_L),
        ]
        plages += [
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.4" fill="url(#or-v)"/>' % (c - PLAGE_L / 2, a, PLAGE_L, PLAGE_P),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.4" fill="url(#or-v)"/>' % (c - PLAGE_L / 2, b - PLAGE_P, PLAGE_L, PLAGE_P),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.4" fill="url(#or-h)"/>' % (a, c - PLAGE_L / 2, PLAGE_P, PLAGE_L),
            '<rect x="%.2f" y="%.2f" width="%s" height="%s" rx="1.4" fill="url(#or-h)"/>' % (b - PLAGE_P, c - PLAGE_L / 2, PLAGE_P, PLAGE_L),
        ]
    return "".join(pattes), "".join(plages)


def svg(variante="couleur"):
    p = PALETTES[variante]
    pattes, plages = broches()
    points = "".join(
        '<circle cx="%s" cy="%s" r="1.7" fill="%s" opacity=".9"/>' % (x, y, p["point"])
        for x, y in [(44, 44), (156, 44), (44, 156), (156, 156)]
    )
    liseres = (
        '<rect x="34.6" y="34.6" width="130.8" height="130.8" rx="13.4" fill="none" '
        'stroke="#FFFFFF" stroke-opacity=".18" stroke-width="1.2"/>'
    ) if p["liseres"] else ""
    biseau = (
        '<rect x="56.3" y="56.3" width="87.4" height="87.4" rx="12.8" fill="none" '
        'stroke="#FFFFFF" stroke-opacity=".9" stroke-width="1.6"/>'
        '<rect x="55" y="55" width="90" height="90" rx="14" fill="url(#biseau)"/>'
    ) if p["liseres"] else ""
    ombre_gravure = (
        '<rect x="51.75" y="51.75" width="96.5" height="96.5" rx="13" fill="none" '
        'stroke="#000000" stroke-opacity=".18" stroke-width="1"/>'
    ) if p["liseres"] else ""
    filtre = 'filter="url(#ombre)"' if p["liseres"] else ""
    relief = (
        '<path d="%s" transform="%s translate(2.5 2.5)" fill="%s" opacity="%s"/>'
        % (GLYPHE, CADRAGE, p["relief"], p["relief_opacite"])
    ) if p["relief_opacite"] != "0" else ""

    # Variante monochrome sur fond fonce : la touche centrale est une reserve
    # (trou dans la marque) pour que le monogramme reste lisible.
    if p["reserve"]:
        masque = (
            '<mask id="reserve"><rect width="200" height="200" fill="#FFFFFF"/>'
            '<rect x="55" y="55" width="90" height="90" rx="14" fill="#000000"/></mask>'
        )
        ouvre, ferme, touche_bloc, points = '<g mask="url(#reserve)">', "</g>", "", ""
    else:
        masque = ""
        ouvre, ferme = "", ""
        touche_bloc = '<g %s><rect x="55" y="55" width="90" height="90" rx="14" fill="url(#touche)"/></g>' % filtre

    return """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200" role="img" aria-label="Auditron X">
  <title>Auditron X</title>
  <defs>
    {g_vert}
    {g_or_v}
    {g_or_h}
    {g_patte_v}
    {g_patte_h}
    {g_touche}
    {g_encre}
    <linearGradient id="biseau" x1="0" y1="0" x2=".35" y2="1">
      <stop offset="0" stop-color="#000000" stop-opacity="0"/>
      <stop offset=".72" stop-color="#000000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000000" stop-opacity=".16"/>
    </linearGradient>
    <filter id="ombre" x="-25%" y="-25%" width="150%" height="150%">
      <feDropShadow dx="0" dy="2.2" stdDeviation="2.2" flood-color="#00180F" flood-opacity=".45"/>
    </filter>
    {masque}
  </defs>

  {ouvre}
  <!-- pattes metalliques -->
  <g>{pattes}</g>

  <!-- corps du circuit -->
  <rect x="34" y="34" width="132" height="132" rx="14" fill="url(#vert)"/>
  {liseres}
  <g>{plages}</g>
  {points}

  <!-- gravure interieure -->
  <rect x="51.75" y="51.75" width="96.5" height="96.5" rx="13" fill="none" stroke="{gravure}" stroke-width="5.5"/>
  {ombre_gravure}
  {ferme}

  <!-- touche -->
  {touche_bloc}
  {biseau}

  <!-- monogramme AX -->
  {relief}
  <path d="{glyphe}" transform="{cadrage} translate(1.2 1.2)" fill="url(#encre)"/>
</svg>
""".format(
        g_vert=degrade("vert", p["vert"]),
        g_or_v=degrade("or-v", p["or_"]),
        g_or_h=degrade("or-h", p["or_"], horizontal=True),
        g_patte_v=degrade("patte-v", p["patte"], horizontal=True),
        g_patte_h=degrade("patte-h", p["patte"]),
        g_touche=degrade("touche", p["touche"]),
        g_encre=degrade("encre", p["encre"]),
        pattes=pattes, plages=plages, points=points, liseres=liseres,
        gravure=p["gravure"], ombre_gravure=ombre_gravure,
        biseau=biseau, relief=relief, glyphe=GLYPHE, cadrage=CADRAGE,
        masque=masque, ouvre=ouvre, ferme=ferme, touche_bloc=touche_bloc,
    )


def main():
    fichiers = {}
    for variante, suffixe in (("couleur", ""), ("mono", "-mono"), ("blanc", "-blanc")):
        chemin = OUT / ("auditron-x-logo%s.svg" % suffixe)
        chemin.write_text(svg(variante), encoding="utf-8")
        fichiers[variante] = chemin
        print("SVG  ", chemin.name)

    try:
        import resvg_py
    except ImportError:
        print("\nresvg-py absent : exports PNG ignores (pip install resvg-py)")
        return

    def rendre(source, taille, cible):
        data = resvg_py.svg_to_bytes(svg_path=str(source), width=taille, height=taille)
        if isinstance(data, list):
            data = bytes(data)
        cible.write_bytes(data)
        print("PNG  ", cible.name, "(%.0f Ko)" % (len(data) / 1024))

    png = OUT / "png"
    png.mkdir(exist_ok=True)
    for taille in (512, 1024, 2048, 4096):
        rendre(fichiers["couleur"], taille, png / ("auditron-x-logo-%d.png" % taille))
    for variante in ("mono", "blanc"):
        rendre(fichiers[variante], 2048, png / ("auditron-x-logo-%s-2048.png" % variante))


if __name__ == "__main__":
    main()
