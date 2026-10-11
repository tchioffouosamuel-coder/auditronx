import { useCallback, useEffect, useState } from "react";
import DataTable from "../../components/DataTable";
import LoadingState from "../../components/LoadingState";
import apiCentral from "../../lib/apiCentral";
import { todayIso } from "../../lib/datetime";

function Tuile({ libelle, valeur, detail, tone = "neutral" }) {
  const tons = {
    neutral: "text-ink-900",
    brand: "text-brand-800",
    amber: "text-amber-600",
    red: "text-red-600",
  };

  return (
    <div className="card p-4">
      <p className="text-xs font-bold tracking-wide text-ink-500 uppercase">
        {libelle}
      </p>
      <p className={`mt-1 text-2xl font-bold tabular-nums ${tons[tone]}`}>
        {valeur}
      </p>
      {detail && <p className="text-xs text-ink-500">{detail}</p>}
    </div>
  );
}

/**
 * Présences de tout le parc, vues depuis le portail éditeur.
 *
 * Le support répondait jusqu'ici à « est-ce que nos pointages remontent ? » en
 * demandant une capture d'écran au client, ou en se faisant inviter dans son
 * établissement. Cet écran répond pour tous les abonnés d'un coup.
 *
 * Deux colonnes portent l'essentiel du diagnostic :
 *  - « injoignable », qui distingue une base qui ne répond pas d'un lycée sans
 *    activité — afficher 0 confondrait les deux ;
 *  - « sans départ », une arrivée jamais suivie d'un départ, signe le plus
 *    courant d'une borne qui a cessé de remonter en cours de journée.
 */
export default function PresencesPage() {
  const [donnees, setDonnees] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [chargement, setChargement] = useState(true);
  const [debut, setDebut] = useState(todayIso);
  const [fin, setFin] = useState(todayIso);
  const [avecLignes, setAvecLignes] = useState(false);
  // Établissement déplié, pour ne pas empiler cinq tableaux nominatifs.
  const [deplie, setDeplie] = useState(null);

  const charger = useCallback(() => {
    setChargement(true);
    setErreur(null);

    apiCentral
      .get("/presences", {
        params: {
          date_debut: debut,
          date_fin: fin,
          avec_lignes: avecLignes ? 1 : undefined,
        },
      })
      .then(({ data }) => setDonnees(data))
      .catch(() => setErreur("Chargement impossible."))
      .finally(() => setChargement(false));
  }, [debut, fin, avecLignes]);

  useEffect(charger, [charger]);

  const etablissements = donnees?.etablissements ?? [];
  const totaux = donnees?.totaux;

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="page-title">Présences du parc</h1>
          <p className="page-subtitle">
            Relevé consolidé de tous les établissements abonnés.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-2">
          <label className="text-xs font-semibold text-ink-500">
            <span className="mb-1 block">Du</span>
            <input
              type="date"
              value={debut}
              onChange={(e) => {
                setDebut(e.target.value);
                // Une date de fin antérieure ferait refuser la requête (422).
                if (e.target.value > fin) setFin(e.target.value);
              }}
              className="field py-2"
            />
          </label>
          <label className="text-xs font-semibold text-ink-500">
            <span className="mb-1 block">au</span>
            <input
              type="date"
              value={fin}
              min={debut}
              onChange={(e) => setFin(e.target.value)}
              className="field py-2"
            />
          </label>
          <button
            type="button"
            onClick={() => {
              setDebut(todayIso());
              setFin(todayIso());
            }}
            className="btn-secondary"
          >
            Aujourd’hui
          </button>
        </div>
      </div>

      {erreur && (
        <div
          role="alert"
          className="card border-red-200 bg-red-50/90 px-4 py-3 text-sm font-medium text-red-700"
        >
          {erreur}
        </div>
      )}

      {totaux && (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <Tuile
            libelle="Établissements"
            valeur={totaux.etablissements}
            detail={
              totaux.injoignables > 0
                ? `${totaux.injoignables} injoignable(s)`
                : "tous joignables"
            }
            tone={totaux.injoignables > 0 ? "red" : "neutral"}
          />
          <Tuile libelle="Effectif" valeur={totaux.effectif} />
          <Tuile
            libelle="Pointages"
            valeur={totaux.pointages}
            tone="brand"
            detail="sur la période"
          />
          <Tuile libelle="Arrivées" valeur={totaux.arrivees} />
          <Tuile
            libelle="Sans départ"
            valeur={totaux.sans_depart}
            tone={totaux.sans_depart > 0 ? "amber" : "neutral"}
            detail="arrivée non suivie d’un départ"
          />
        </div>
      )}

      {/*
        Les totaux excluent les établissements injoignables : les additionner à
        zéro ferait passer une panne pour une journée calme. On le dit à
        l'écran, sinon le chiffre paraît faux.
      */}
      {totaux?.injoignables > 0 && (
        <p className="text-xs font-medium text-amber-700">
          Les totaux portent sur les {totaux.etablissements - totaux.injoignables}{" "}
          établissement(s) joignable(s) : les bases qui ne répondent pas sont
          exclues plutôt que comptées à zéro.
        </p>
      )}

      {chargement && !donnees ? (
        <LoadingState label="Interrogation des établissements" />
      ) : (
        <DataTable
          loading={chargement}
          rows={etablissements}
          idKey="code"
          emptyMessage="Aucun établissement provisionné."
          searchPlaceholder="Rechercher un établissement…"
          pageSize={25}
          columns={[
            {
              key: "nom",
              label: "Établissement",
              render: (e) => (
                <span>
                  <span className="font-semibold text-ink-800">{e.nom}</span>
                  <span className="ml-2 font-mono text-xs text-ink-400">
                    {e.code}
                  </span>
                </span>
              ),
              searchValue: (e) => `${e.nom} ${e.code}`,
              sortValue: (e) => e.nom ?? "",
            },
            {
              key: "effectif",
              label: "Effectif",
              align: "right",
              render: (e) => (e.injoignable ? "—" : e.effectif),
              sortValue: (e) => e.effectif ?? -1,
            },
            {
              key: "pointages",
              label: "Pointages",
              align: "right",
              render: (e) =>
                e.injoignable ? (
                  "—"
                ) : (
                  <span className="font-semibold text-brand-800 tabular-nums">
                    {e.pointages}
                  </span>
                ),
              sortValue: (e) => e.pointages ?? -1,
            },
            {
              key: "arrivees",
              label: "Arrivées",
              align: "right",
              render: (e) => (e.injoignable ? "—" : e.arrivees),
              sortValue: (e) => e.arrivees ?? -1,
            },
            {
              key: "departs",
              label: "Départs",
              align: "right",
              render: (e) => (e.injoignable ? "—" : e.departs),
              sortValue: (e) => e.departs ?? -1,
            },
            {
              key: "sans_depart",
              label: "Sans départ",
              align: "right",
              render: (e) =>
                e.injoignable ? (
                  "—"
                ) : e.sans_depart > 0 ? (
                  <span className="badge bg-amber-50 text-amber-700 tabular-nums">
                    {e.sans_depart}
                  </span>
                ) : (
                  <span className="text-ink-300">0</span>
                ),
              sortValue: (e) => e.sans_depart ?? -1,
            },
            {
              key: "dernier_pointage",
              label: "Dernier pointage",
              render: (e) =>
                e.injoignable ? "—" : (e.dernier_pointage ?? "jamais"),
              sortValue: (e) => e.dernier_pointage ?? "",
            },
            {
              key: "etat",
              label: "État",
              render: (e) =>
                e.injoignable ? (
                  <span className="badge bg-red-50 text-red-700">
                    Injoignable
                  </span>
                ) : e.statut === "actif" ? (
                  <span className="badge bg-green-50 text-green-700">Actif</span>
                ) : (
                  <span className="badge bg-ink-50 text-ink-600">
                    {e.statut}
                  </span>
                ),
              searchValue: (e) => (e.injoignable ? "injoignable" : e.statut),
            },
          ]}
          renderActions={(e) =>
            !e.injoignable && (
              <button
                type="button"
                onClick={() => {
                  setAvecLignes(true);
                  setDeplie(deplie === e.code ? null : e.code);
                }}
                className="btn-ghost text-xs"
              >
                {deplie === e.code ? "Masquer" : "Détail"}
              </button>
            )
          }
          actionsLabel=""
        />
      )}

      {/*
        Le détail nominatif est demandé explicitement : il fait remonter les
        noms des agents de tous les établissements, ce qui n'a pas à circuler
        par défaut sur l'écran de supervision d'un éditeur.
      */}
      {deplie && (
        <DetailEtablissement
          etablissement={etablissements.find((e) => e.code === deplie)}
          chargement={chargement}
        />
      )}
    </div>
  );
}

function DetailEtablissement({ etablissement, chargement }) {
  if (!etablissement) return null;

  const lignes = etablissement.lignes;

  if (chargement && !lignes) {
    return <LoadingState label={`Détail de ${etablissement.code}`} />;
  }

  return (
    <section className="card p-4 sm:p-6">
      <h2 className="section-title mb-3">
        {etablissement.nom}{" "}
        <span className="font-mono text-xs text-ink-400">
          {etablissement.code}
        </span>
      </h2>

      {etablissement.lignes_tronquees && (
        <p className="mb-3 text-xs font-medium text-amber-700">
          Relevé tronqué : seules les premières lignes sont remontées. Affinez
          la période pour tout voir.
        </p>
      )}

      <DataTable
        rows={lignes ?? []}
        getRowKey={(l, i) => `${l.date}-${l.matricule ?? i}`}
        emptyMessage="Aucun pointage sur la période."
        searchPlaceholder="Nom, matricule, section…"
        columns={[
          { key: "date", label: "Date" },
          {
            key: "nom",
            label: "Agent",
            render: (l) => l.nom ?? "—",
          },
          { key: "matricule", label: "Matricule" },
          { key: "section", label: "Section" },
          {
            key: "heure_arrivee",
            label: "Arrivée",
            render: (l) => l.heure_arrivee ?? "—",
          },
          {
            key: "heure_depart",
            label: "Départ",
            render: (l) =>
              l.heure_depart ?? (
                <span className="badge bg-amber-50 text-amber-700">
                  manquant
                </span>
              ),
            searchValue: (l) => l.heure_depart ?? "manquant",
          },
          { key: "source", label: "Source" },
        ]}
      />
    </section>
  );
}
