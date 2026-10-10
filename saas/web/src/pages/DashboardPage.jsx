import { useEffect, useState } from "react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import api from "../lib/api";
import { todayIso } from "../lib/datetime";

/** Palette des indicateurs, alignée sur l'identité Auditron. */
const TONS = {
  neutral: {
    valeur: "text-brand-800",
    barre: "bg-brand-600",
    icone: "text-brand-600 bg-brand-100",
    titre: "text-ink-700",
  },
  green: {
    valeur: "text-green-600",
    barre: "bg-green-500",
    icone: "text-green-600 bg-green-50",
    titre: "text-green-700",
  },
  red: {
    valeur: "text-red-600",
    barre: "bg-red-500",
    icone: "text-red-600 bg-red-50",
    titre: "text-red-700",
  },
  amber: {
    valeur: "text-amber-600",
    barre: "bg-amber-500",
    icone: "text-amber-600 bg-amber-50",
    titre: "text-amber-700",
  },
};

function Icone({ nom, className = "" }) {
  return (
    <span
      aria-hidden="true"
      className={`material-symbols-rounded text-[20px] ${className}`}
    >
      {nom}
    </span>
  );
}

function KpiCard({ label, value, icon, tone = "neutral", total }) {
  const t = TONS[tone] ?? TONS.neutral;

  /* Part de l'effectif : « 42 absents » ne dit rien sans le dénominateur, et
     la barre donne l'ordre de grandeur sans avoir à faire le calcul. */
  const part =
    typeof total === "number" && total > 0 && typeof value === "number"
      ? Math.round((value / total) * 100)
      : null;

  return (
    <div className="card-interactive p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="text-xs font-bold tracking-wide text-ink-400 uppercase">
          {label}
        </div>
        <span className={`rounded-lg p-1.5 ${t.icone}`}>
          <Icone nom={icon} />
        </span>
      </div>
      <div className={`mt-1 text-3xl font-black tabular-nums ${t.valeur}`}>
        {value ?? "—"}
      </div>
      {part !== null ? (
        <>
          <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100">
            <div
              className={`h-full rounded-full ${t.barre}`}
              style={{ width: `${Math.min(100, part)}%` }}
            />
          </div>
          <div className="mt-1 text-xs font-medium text-ink-400 tabular-nums">
            {part}% de l’effectif
          </div>
        </>
      ) : (
        <div className="mt-2 text-xs font-medium text-ink-400">
          personnes attendues
        </div>
      )}
    </div>
  );
}

/** Couleur de la barre selon le taux : lecture immédiate des sections en peine. */
function couleurTaux(taux) {
  if (taux >= 90) return "#059669";
  if (taux >= 70) return "#5a3e8e";
  if (taux >= 50) return "#ea8365";
  return "#dc2626";
}

function InfobulleTaux({ active, payload, label }) {
  if (!active || !payload?.length) return null;

  return (
    <div className="rounded-xl border border-brand-200 bg-white px-3 py-2 text-xs shadow-float">
      <div className="font-bold text-ink-900">{label}</div>
      <div className="mt-0.5 font-semibold tabular-nums text-brand-700">
        {payload[0].value}% d’assiduité
      </div>
    </div>
  );
}

function PeopleList({ title, icon, people = [], tone = "neutral" }) {
  const t = TONS[tone] ?? TONS.neutral;

  return (
    <section className="card p-4 sm:p-6">
      <h2 className={`mb-4 flex items-center gap-2 text-sm font-bold ${t.titre}`}>
        <span className={`rounded-lg p-1 ${t.icone}`}>
          <Icone nom={icon} className="text-[18px]" />
        </span>
        {title}
        <span className="badge bg-ink-50 text-ink-500 tabular-nums">
          {people.length}
        </span>
      </h2>
      {people.length === 0 ? (
        <p className="py-4 text-center text-sm text-ink-300">Aucune personne.</p>
      ) : (
        <div className="-mx-4 overflow-x-auto sm:mx-0">
          <table className="min-w-full px-4 text-left text-sm">
            <thead className="border-b border-ink-100 text-xs tracking-wide text-ink-400 uppercase">
              <tr>
                <th className="px-2 py-2 font-bold">Nom</th>
                <th className="px-2 py-2 font-bold">Matricule</th>
                <th className="px-2 py-2 font-bold">Cours prévu</th>
                <th className="px-2 py-2 font-bold">Arrivée</th>
                <th className="px-2 py-2 font-bold">Départ</th>
                <th className="px-2 py-2 font-bold">Retard</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-100">
              {people.map((person) => (
                <tr
                  key={person.enseignant_id}
                  className="transition-colors hover:bg-brand-50/60"
                >
                  <td className="px-2 py-3 font-semibold whitespace-nowrap text-ink-800">
                    {person.nom || "—"}
                  </td>
                  <td className="px-2 py-3 whitespace-nowrap text-ink-500 tabular-nums">
                    {person.matricule || "—"}
                  </td>
                  <td className="px-2 py-3 text-ink-500">
                    {person.hors_emploi_du_temps ? (
                      <span className="text-ink-300 italic">
                        Pas de cours ce jour
                      </span>
                    ) : person.horaire_administratif ? (
                      `Administration ${person.horaire_administratif.heure_debut}-${person.horaire_administratif.heure_fin}`
                    ) : (
                      (person.cours || [])
                        .map(
                          (course) =>
                            `${course.classe || "—"} ${course.heure_debut || ""}-${course.heure_fin || ""}`,
                        )
                        .join(" · ") || "—"
                    )}
                  </td>
                  <td className="px-2 py-3 whitespace-nowrap text-ink-500 tabular-nums">
                    {person.heure_arrivee || "—"}
                  </td>
                  <td className="px-2 py-3 whitespace-nowrap text-ink-500 tabular-nums">
                    {person.heure_depart || "—"}
                  </td>
                  <td className="px-2 py-3 whitespace-nowrap">
                    {person.minutes_retard ? (
                      <span className="badge bg-amber-50 text-amber-700 tabular-nums">
                        {person.minutes_retard} min
                      </span>
                    ) : (
                      <span className="text-ink-300">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export default function DashboardPage() {
  const [data, setData] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [date, setDate] = useState(todayIso);

  useEffect(() => {
    let annule = false;

    setData(null);
    setErreur(null);

    api
      .get("/dashboard", { params: { date } })
      .then(({ data }) => {
        if (!annule) setData(data);
      })
      /*
        Sans ce `catch`, une requête en échec laissait les squelettes animés
        tourner indéfiniment : impossible de distinguer « ça charge » de
        « l'API ne répond pas ».
      */
      .catch(() => {
        if (!annule) setErreur("Impossible de charger le tableau de bord.");
      });

    return () => {
      annule = true;
    };
  }, [date]);

  const classement =
    data && Array.isArray(data.classement_par_section)
      ? data.classement_par_section
      : [];
  const scannes = data && Array.isArray(data.scannes) ? data.scannes : [];
  const absents =
    data && Array.isArray(data.absents_liste) ? data.absents_liste : [];
  const retardataires =
    data && Array.isArray(data.retardataires_liste)
      ? data.retardataires_liste
      : [];

  const estAujourdhui = date === todayIso();

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="page-title">Tableau de bord</h1>
          <p className="page-subtitle">
            {estAujourdhui
              ? "Présences du jour, mises à jour à chaque scan."
              : "Présences de la date sélectionnée."}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <label className="text-xs font-semibold text-ink-500" htmlFor="date">
            Date
          </label>
          <input
            id="date"
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="field py-2"
          />
          {!estAujourdhui && (
            <button
              type="button"
              onClick={() => setDate(todayIso())}
              className="btn-secondary"
            >
              Aujourd’hui
            </button>
          )}
        </div>
      </div>

      {erreur && (
        <div
          role="alert"
          className="card flex items-center gap-2 border-red-200 bg-red-50/90 px-4 py-3 text-sm font-medium text-red-700"
        >
          <Icone nom="cloud_off" />
          {erreur}
        </div>
      )}

      {!data && !erreur && (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-32 animate-pulse rounded-2xl border border-brand-200/70 bg-white/60"
            />
          ))}
        </div>
      )}

      {data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <KpiCard label="Effectif" value={data.effectif} icon="groups" />
            <KpiCard
              label="Présents"
              value={data.presents}
              total={data.effectif}
              icon="task_alt"
              tone="green"
            />
            <KpiCard
              label="Absents"
              value={data.absents}
              total={data.effectif}
              icon="person_off"
              tone="red"
            />
            <KpiCard
              label="Retardataires"
              value={data.retardataires}
              total={data.effectif}
              icon="schedule"
              tone="amber"
            />
          </div>

          <div className="card p-4 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
              <h2 className="section-title">Taux d’assiduité par section</h2>
              {/* Légende : la couleur des barres porte une information, elle
                  doit être explicitée. */}
              {classement.length > 0 && (
                <div className="flex flex-wrap items-center gap-3 text-xs text-ink-400">
                  {[
                    ["≥ 90 %", "#059669"],
                    ["70–89 %", "#5a3e8e"],
                    ["50–69 %", "#ea8365"],
                    ["< 50 %", "#dc2626"],
                  ].map(([libelle, couleur]) => (
                    <span key={libelle} className="flex items-center gap-1.5">
                      <span
                        aria-hidden="true"
                        className="h-2.5 w-2.5 rounded-sm"
                        style={{ background: couleur }}
                      />
                      {libelle}
                    </span>
                  ))}
                </div>
              )}
            </div>
            {classement.length === 0 ? (
              <div className="flex h-[280px] flex-col items-center justify-center gap-1 text-sm text-ink-300">
                <Icone nom="bar_chart" className="text-[32px] text-ink-200" />
                Aucune donnée pour cette date.
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={280}>
                <BarChart
                  data={classement}
                  margin={{ top: 4, right: 4, bottom: 0, left: -16 }}
                >
                  <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="var(--color-ink-100)"
                    vertical={false}
                  />
                  <XAxis
                    dataKey="section"
                    tick={{ fontSize: 12, fill: "var(--color-ink-500)" }}
                    stroke="var(--color-ink-200)"
                  />
                  <YAxis
                    domain={[0, 100]}
                    tick={{ fontSize: 12, fill: "var(--color-ink-500)" }}
                    stroke="var(--color-ink-200)"
                    unit="%"
                  />
                  <Tooltip
                    content={<InfobulleTaux />}
                    cursor={{ fill: "var(--color-brand-100)" }}
                  />
                  {/*
                    L'ancien remplissage était un vert (#0f6e49) hérité d'une
                    palette abandonnée : seule tache verte d'une interface
                    violette. La couleur sert maintenant à qualifier le taux.
                  */}
                  <Bar dataKey="taux_assiduite" radius={[6, 6, 0, 0]}>
                    {classement.map((ligne) => (
                      <Cell
                        key={ligne.section}
                        fill={couleurTaux(Number(ligne.taux_assiduite) || 0)}
                      />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>

          <div className="mt-6 space-y-6">
            <PeopleList
              title="Déjà scannés"
              icon="task_alt"
              people={scannes}
              tone="green"
            />
            <PeopleList
              title="Absents selon l’emploi du temps"
              icon="person_off"
              people={absents}
              tone="red"
            />
            <PeopleList
              title="Retardataires"
              icon="schedule"
              people={retardataires}
              tone="amber"
            />
          </div>
        </>
      )}
    </div>
  );
}
