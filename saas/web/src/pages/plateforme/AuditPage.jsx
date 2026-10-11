import { useEffect, useState } from "react";
import DataTable from "../../components/DataTable";
import { formatDateTime, startOfMonthIso, todayIso } from "../../lib/datetime";
import apiCentral from "../../lib/apiCentral";

const PAGE_SIZE = 500;
const MAX_PAGES = 40;

export default function AuditPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [debut, setDebut] = useState(startOfMonthIso);
  const [fin, setFin] = useState(todayIso);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError("");
      try {
        const entries = [];
        let page = 1;
        let lastPage = 1;
        do {
          const { data } = await apiCentral.get("/audit-logs", {
            params: { debut, fin, page, per_page: PAGE_SIZE },
          });
          entries.push(...(data.data ?? []));
          lastPage = Number(data.last_page ?? 1);
          page += 1;
        } while (page <= lastPage && page <= MAX_PAGES);

        if (!cancelled) setRows(entries);
      } catch (err) {
        if (!cancelled) {
          setError(
            err.response?.status === 403
              ? "Le journal Auditron est réservé aux super-administrateurs."
              : "Impossible de charger le journal Auditron.",
          );
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    load();
    return () => {
      cancelled = true;
    };
  }, [debut, fin]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="page-title">Journal d’audit Auditron</h1>
        <p className="page-subtitle">
          Actions et consultations du portail central, y compris les tentatives
          refusées. Les mots de passe et jetons sont masqués.
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Du</span>
          <input
            type="date"
            value={debut}
            onChange={(event) => setDebut(event.target.value)}
            className="field py-2"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Au</span>
          <input
            type="date"
            value={fin}
            onChange={(event) => setFin(event.target.value)}
            className="field py-2"
          />
        </label>
      </div>

      {error && (
        <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </div>
      )}

      <DataTable
        columns={[
          {
            key: "created_at",
            label: "Quand",
            render: (entry) => formatDateTime(entry.created_at),
            sortValue: (entry) => entry.created_at ?? "",
          },
          {
            key: "auteur_nom",
            label: "Qui",
            render: (entry) =>
              `${entry.auteur_nom ?? "Inconnu"}${entry.auteur_email ? ` · ${entry.auteur_email}` : ""}`,
            searchValue: (entry) =>
              `${entry.auteur_nom ?? ""} ${entry.auteur_email ?? ""}`,
          },
          { key: "action", label: "Action" },
          {
            key: "sujet_libelle",
            label: "Sur quoi",
            render: (entry) => entry.sujet_libelle ?? "—",
          },
          {
            key: "requete",
            label: "Requête",
            render: (entry) =>
              `${entry.methode ?? "—"} ${entry.route ?? entry.url ?? ""}${entry.statut ? ` · ${entry.statut}` : ""}`,
          },
          { key: "ip", label: "Adresse IP", render: (entry) => entry.ip ?? "—" },
          {
            key: "contexte",
            label: "Détails",
            sortable: false,
            render: (entry) => (
              <details>
                <summary className="cursor-pointer text-brand-700">Consulter</summary>
                <pre className="mt-2 max-h-56 max-w-xl overflow-auto whitespace-pre-wrap rounded-lg bg-ink-50 p-2 text-xs">
                  {JSON.stringify(
                    { changements: entry.changements, contexte: entry.contexte },
                    null,
                    2,
                  )}
                </pre>
              </details>
            ),
          },
        ]}
        rows={rows}
        loading={loading}
        emptyMessage="Aucune action pour cette période."
      />
    </div>
  );
}
