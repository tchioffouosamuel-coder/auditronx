import { useEffect, useState } from "react";
import DataTable from "../components/DataTable";
import JournalHebdomadaire from "../components/JournalHebdomadaire";
import Modal from "../components/Modal";
import api from "../lib/api";
import { formatTime, todayIso } from "../lib/datetime";
import { downloadFile } from "../lib/download";

/**
 * Les deux catégories de personnel ne partagent jamais un journal : leurs
 * horaires attendus n'ont pas la même source (emploi du temps pour les
 * enseignants, horaire fixe pour l'administration) et les documents produits
 * sont visés par des responsables différents. Le choix est donc explicite et
 * toujours transmis à l'API, y compris aux exports.
 */
const CATEGORIES = [
  ["enseignant", "Personnel enseignant"],
  ["administration", "Personnel administratif"],
];

function SelecteurCategorie({ valeur, onChange }) {
  return (
    <div className="inline-flex rounded-md border border-ink-100 bg-white p-0.5">
      {CATEGORIES.map(([cle, libelle]) => (
        <button
          key={cle}
          type="button"
          onClick={() => onChange(cle)}
          className={`rounded px-3 py-1 text-sm transition ${
            valeur === cle
              ? "bg-brand-700 text-white"
              : "text-ink-700 hover:bg-ink-50"
          }`}
        >
          {libelle}
        </button>
      ))}
    </div>
  );
}

/** Mois courant au format `YYYY-MM`, valeur attendue par `<input type="month">`. */
function moisCourant() {
  return todayIso().slice(0, 7);
}

function StatsTab() {
  const [lignes, setLignes] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .get("/assiduite/stats")
      .then(({ data }) => setLignes(Array.isArray(data) ? data : []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <DataTable
      loading={loading}
      rows={lignes}
      idKey="enseignant_id"
      columns={[
        { key: "nom", label: "Nom" },
        { key: "section", label: "Section" },
        { key: "jours_presents", label: "Jours présents" },
        {
          key: "jours_attendus",
          label: "Jours attendus selon l’emploi du temps",
        },
        {
          key: "taux_assiduite",
          label: "Taux",
          render: (l) => `${l.taux_assiduite}%`,
          sortValue: (l) => l.taux_assiduite,
        },
      ]}
    />
  );
}

function JournalTab() {
  // Deux périodes dans le même onglet : le journal du jour reste la vue par
  // défaut (consultation courante), la grille hebdomadaire sert aux bilans et
  // à l'archivage papier.
  const [periode, setPeriode] = useState("jour");
  const [categorie, setCategorie] = useState("enseignant");

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center gap-3">
        <SelecteurCategorie valeur={categorie} onChange={setCategorie} />
      </div>

      <div className="mb-3 inline-flex rounded-md border border-ink-100 bg-white p-0.5">
        {[
          ["jour", "Par jour"],
          ["semaine", "Par semaine"],
        ].map(([cle, libelle]) => (
          <button
            key={cle}
            type="button"
            onClick={() => setPeriode(cle)}
            className={`rounded px-3 py-1 text-sm transition ${
              periode === cle
                ? "bg-brand-700 text-white"
                : "text-ink-700 hover:bg-ink-50"
            }`}
          >
            {libelle}
          </button>
        ))}
      </div>

      {periode === "jour" ? (
        <JournalDuJour categorie={categorie} />
      ) : (
        <JournalHebdomadaire categorie={categorie} />
      )}
    </div>
  );
}

function JournalDuJour({ categorie }) {
  const [date, setDate] = useState(todayIso);
  const [presences, setPresences] = useState([]);
  const [loading, setLoading] = useState(true);
  const [photoPreview, setPhotoPreview] = useState(null);

  useEffect(() => {
    setLoading(true);
    api
      .get("/assiduite/journal", { params: { date, categorie } })
      .then(({ data }) => setPresences(Array.isArray(data) ? data : []))
      .finally(() => setLoading(false));
  }, [date, categorie]);

  return (
    <div>
      <div className="mb-3 flex items-center gap-3">
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className="field py-2"
        />
        <button
          type="button"
          onClick={() =>
            downloadFile(
              `/assiduite/journal/pdf?date=${date}&categorie=${categorie}`,
              `journal-${categorie}-${date}.pdf`,
            )
          }
          className="btn-secondary"
        >
          Journal en PDF
        </button>
        <button
          type="button"
          onClick={() =>
            downloadFile(
              `/assiduite/journal/word?date=${date}&categorie=${categorie}`,
              `journal-${categorie}-${date}.docx`,
            )
          }
          className="btn-primary"
        >
          Journal en Word
        </button>
      </div>
      <DataTable
        loading={loading}
        rows={presences}
        columns={[
          {
            key: "enseignant",
            label: "Enseignant",
            render: (p) => p.enseignant?.nom,
            sortValue: (p) => p.enseignant?.nom,
          },
          {
            key: "heure_arrivee",
            label: "Arrivée",
            render: (p) =>
              p.heure_arrivee
                ? formatTime(p.heure_arrivee)
                : "—",
            sortValue: (p) => p.heure_arrivee ?? "",
          },
          {
            key: "heure_depart",
            label: "Départ",
            render: (p) =>
              p.heure_depart
                ? formatTime(p.heure_depart)
                : "—",
            sortValue: (p) => p.heure_depart ?? "",
          },
          { key: "source", label: "Source" },
          {
            key: "photo",
            label: "Photo (§hardware)",
            sortable: false,
            render: (p) => (
              <div className="flex gap-2">
                {p.photo_url_arrivee && (
                  <button
                    type="button"
                    onClick={() =>
                      setPhotoPreview({
                        url: p.photo_url_arrivee,
                        title: "Photo à l'arrivée",
                      })
                    }
                    title="Photo à l'arrivée"
                  >
                    <img
                      src={p.photo_url_arrivee}
                      alt="Photo arrivée"
                      className="h-10 w-10 rounded-md border border-ink-100 object-cover transition hover:scale-150 hover:shadow-md"
                    />
                  </button>
                )}
                {p.photo_url_depart && (
                  <button
                    type="button"
                    onClick={() =>
                      setPhotoPreview({
                        url: p.photo_url_depart,
                        title: "Photo au départ",
                      })
                    }
                    title="Photo au départ"
                  >
                    <img
                      src={p.photo_url_depart}
                      alt="Photo départ"
                      className="h-10 w-10 rounded-md border border-ink-100 object-cover transition hover:scale-150 hover:shadow-md"
                    />
                  </button>
                )}
                {!p.photo_url_arrivee && !p.photo_url_depart && (
                  <span className="text-ink-300">—</span>
                )}
              </div>
            ),
          },
        ]}
      />
      {photoPreview && (
        <Modal title={photoPreview.title} onClose={() => setPhotoPreview(null)}>
          <img
            src={photoPreview.url}
            alt={photoPreview.title}
            className="max-h-[75vh] w-full rounded-md object-contain"
          />
        </Modal>
      )}
    </div>
  );
}

/**
 * Les trois bilans mensuels téléchargeables. Le global réunit assiduité et
 * ponctualité ; les deux autres n'en gardent qu'un volet.
 */
const VOLETS_BILAN = [
  ["global", "Bilan global"],
  ["assiduite", "Assiduité"],
  ["ponctualite", "Ponctualité"],
];

/**
 * Bilan mensuel regroupé par section (§4.2) — la vue que la direction
 * transmet en fin de mois. Les statistiques de l'onglet « Statistiques »
 * restent une liste plate sur une période libre ; ici le mois est l'unité et
 * la section le regroupement, avec un sous-total par section.
 */
function BilanMensuelTab() {
  const [mois, setMois] = useState(moisCourant);
  const [categorie, setCategorie] = useState("enseignant");
  const [bilan, setBilan] = useState(null);
  const [loading, setLoading] = useState(true);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    let annule = false;
    setLoading(true);
    setErreur(null);

    api
      .get("/assiduite/bilan-mensuel", { params: { mois, categorie } })
      .then(({ data }) => {
        if (!annule) setBilan(data);
      })
      .catch(() => {
        if (!annule) setErreur("Impossible de charger le bilan du mois.");
      })
      .finally(() => {
        if (!annule) setLoading(false);
      });

    return () => {
      annule = true;
    };
  }, [mois, categorie]);

  const sections = bilan?.sections ?? [];

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center gap-3">
        <SelecteurCategorie valeur={categorie} onChange={setCategorie} />
        <input
          type="month"
          value={mois}
          onChange={(e) => setMois(e.target.value || moisCourant())}
          className="field py-2"
        />
      </div>

      {/* Trois bilans distincts : le global pour le conseil, les deux volets
          séparés pour les réunions où l'autre n'a pas à circuler. */}
      <div className="mb-4 flex flex-wrap items-center gap-2">
        {VOLETS_BILAN.map(([volet, libelle]) => (
          <div key={volet} className="flex items-center gap-1 rounded-2xl border border-ink-100 px-2 py-1">
            <span className="px-1 text-sm text-ink-700">{libelle}</span>
            {["word", "pdf"].map((format) => (
              <button
                key={format}
                type="button"
                onClick={() =>
                  downloadFile(
                    `/assiduite/bilan-mensuel/${format}?mois=${mois}&categorie=${categorie}&volet=${volet}`,
                    `bilan-mensuel-${volet}-${categorie}-${mois}.${format === "word" ? "docx" : "pdf"}`,
                  )
                }
                className="btn-secondary px-2 py-1 text-xs"
              >
                {format === "word" ? "Word" : "PDF"}
              </button>
            ))}
          </div>
        ))}
      </div>

      {erreur && (
        <div className="mb-4 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
          {erreur}
        </div>
      )}

      {!loading && bilan && (
        <p className="mb-3 text-sm text-ink-700">
          {bilan.libelle} · {bilan.total.effectif} personne
          {bilan.total.effectif > 1 ? "s" : ""} ·{" "}
          <span className="font-medium">{bilan.total.taux_assiduite} %</span> de
          taux global
        </p>
      )}

      {!loading && sections.length === 0 && !erreur && (
        <p className="text-sm text-ink-300">
          Aucun membre du personnel dans cette catégorie pour ce mois.
        </p>
      )}

      {sections.map((section) => (
        <section key={section.section} className="mb-6">
          <h2 className="mb-2 text-sm font-semibold text-ink-900">
            {section.section} · {section.effectif} personne
            {section.effectif > 1 ? "s" : ""} · taux moyen{" "}
            {section.taux_assiduite} %
          </h2>
          <DataTable
            rows={section.lignes}
            idKey="enseignant_id"
            columns={[
              { key: "nom", label: "Personnel" },
              {
                key: "fonction",
                label: "Fonction",
                render: (l) => l.fonction ?? "—",
              },
              { key: "jours_attendus", label: "Jours attendus" },
              { key: "jours_presents", label: "Jours assurés" },
              { key: "jours_signales", label: "Signalements" },
              {
                key: "taux_assiduite",
                label: "Taux",
                render: (l) => (
                  <span
                    title={
                      l.assiduite_garantie
                        ? "Assiduité garantie par la fonction (direction)"
                        : undefined
                    }
                  >
                    {l.taux_assiduite} %{l.assiduite_garantie ? " *" : ""}
                  </span>
                ),
                sortValue: (l) => l.taux_assiduite,
              },
            ]}
          />
        </section>
      ))}

      {loading && <p className="text-sm text-ink-300">Chargement…</p>}

      <p className="mt-2 text-xs text-ink-300">
        * assiduité garantie par la fonction : le chef d’établissement est
        compté présent sur tous ses jours attendus.
      </p>
    </div>
  );
}

function PersonnelInactifTab() {
  const [jours, setJours] = useState(7);
  const [inactifs, setInactifs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .get("/assiduite/personnel-inactif", { params: { jours } })
      .then(({ data }) => setInactifs(Array.isArray(data) ? data : []))
      .finally(() => setLoading(false));
  }, [jours]);

  return (
    <div>
      <label className="mb-3 block text-sm">
        Inactifs depuis plus de{" "}
        <input
          type="number"
          value={jours}
          onChange={(e) => setJours(e.target.value)}
          className="field w-16 px-2 py-1.5"
        />{" "}
        jours
      </label>
      <DataTable
        loading={loading}
        rows={inactifs}
        idKey="enseignant_id"
        columns={[
          { key: "nom", label: "Nom" },
          { key: "section", label: "Section" },
          {
            key: "derniere_presence",
            label: "Dernière présence",
            render: (i) => i.derniere_presence ?? "Jamais",
          },
        ]}
      />
    </div>
  );
}

export default function AssiduitePage() {
  const [tab, setTab] = useState("stats");

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="page-title">
          Classement d’assiduité
        </h1>
        <button
          onClick={() =>
            downloadFile("/statistiques/export-zip", "bilans-retards.zip")
          }
          className="btn-primary"
        >
          Export ZIP (bilans PDF)
        </button>
      </div>

      <div className="mb-4 flex gap-2">
        {[
          ["stats", "Statistiques"],
          ["journal", "Journal des présences"],
          ["mensuel", "Bilan mensuel par section"],
          ["inactif", "Personnel inactif"],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === key
                ? "bg-brand-700 text-white"
                : "bg-white text-ink-700 border border-ink-100"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === "stats" && <StatsTab />}
      {tab === "journal" && <JournalTab />}
      {tab === "mensuel" && <BilanMensuelTab />}
      {tab === "inactif" && <PersonnelInactifTab />}
    </div>
  );
}
