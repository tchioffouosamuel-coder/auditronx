import { useState } from "react";
import api from "../lib/api";
import { formatDateTime } from "../lib/datetime";
import DataTable from "./DataTable";

const STATUS = {
  pending: { label: "À importer", className: "bg-brand-100 text-brand-800" },
  ok: { label: "Importé", className: "bg-green-100 text-green-800" },
  duplicate: { label: "Déjà enregistré", className: "bg-ink-100 text-ink-700" },
  rejected: { label: "Rejeté", className: "bg-red-100 text-red-700" },
  invalid: { label: "Ligne invalide", className: "bg-red-100 text-red-700" },
  retry: { label: "Erreur temporaire", className: "bg-gold-100 text-gold-700" },
};

const TYPE_LABEL = { scan: "Scan", admin_proxy: "Procuration" };

/**
 * Import manuel du fichier `queue.jsonl` d'une borne (carte micro-SD) qui ne
 * parvient pas à synchroniser : analyse d'abord (aucun enregistrement), puis
 * import sur confirmation. Les pointages déjà présents sont ignorés côté API,
 * réimporter le même fichier est donc sans risque.
 */
export default function RelayQueueImport() {
  const [file, setFile] = useState(null);
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function send(dryRun) {
    setBusy(true);
    setError(null);
    try {
      const form = new FormData();
      form.append("file", file);
      form.append("dry_run", dryRun ? "1" : "0");
      const { data } = await api.post("/relay/import", form, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setResult(data);
    } catch (err) {
      setError(
        err.response?.status === 413
          ? "Fichier trop volumineux pour le serveur (limite d'upload PHP)."
          : (err.response?.data?.message ?? "Échec de l'envoi du fichier."),
      );
    } finally {
      setBusy(false);
    }
  }

  function selectFile(e) {
    setFile(e.target.files?.[0] ?? null);
    setResult(null);
    setError(null);
  }

  const pendingCount = result?.dry_run ? (result.summary.pending ?? 0) : 0;

  return (
    <div>
      <div className="mb-4 rounded-lg border border-ink-100 bg-white p-4">
        <p className="mb-3 text-sm text-ink-700">
          Récupérez le fichier <span className="font-mono">queue.jsonl</span> à la racine de la carte micro-SD
          de la borne, puis déposez-le ici. Les pointages déjà enregistrés sont ignorés : réimporter le même
          fichier ne crée aucun doublon.
        </p>
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="file"
            accept=".jsonl,.json,.txt"
            onChange={selectFile}
            disabled={busy}
            className="text-sm file:mr-3 file:rounded-md file:border file:border-ink-100 file:bg-white file:px-3 file:py-1.5 file:text-sm file:text-ink-700 hover:file:bg-ink-50"
          />
          <button
            onClick={() => send(true)}
            disabled={!file || busy}
            className="rounded-md border border-ink-100 px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-50 disabled:opacity-50"
          >
            {busy && !result ? "Analyse…" : "Analyser"}
          </button>
          {pendingCount > 0 && (
            <button
              onClick={() => send(false)}
              disabled={busy}
              className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800 disabled:opacity-50"
            >
              {busy ? "Import en cours…" : `Importer ${pendingCount} pointage(s)`}
            </button>
          )}
        </div>
        {error && <div className="mt-3 text-sm text-red-600">{error}</div>}
      </div>

      {result && (
        <>
          <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
            <span className="font-medium text-ink-900">
              {result.dry_run ? "Analyse" : "Import terminé"} — {result.total} ligne(s) :
            </span>
            {Object.entries(result.summary).map(([status, count]) => (
              <span key={status} className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS[status]?.className ?? ""}`}>
                {STATUS[status]?.label ?? status} : {count}
              </span>
            ))}
          </div>

          <DataTable
            rows={result.lines}
            idKey="line"
            pageSize={25}
            emptyMessage="Fichier vide."
            columns={[
              { key: "line", label: "Ligne", sortValue: (l) => l.line },
              {
                key: "captured_at",
                label: "Scanné le",
                render: (l) => formatDateTime(l.captured_at),
                sortValue: (l) => l.captured_at ?? "",
              },
              { key: "enseignant", label: "Enseignant", render: (l) => l.enseignant ?? "—" },
              { key: "type", label: "Type", render: (l) => TYPE_LABEL[l.type] ?? l.type ?? "—" },
              { key: "has_photo", label: "Selfie", render: (l) => (l.has_photo ? "Oui" : "—") },
              {
                key: "status",
                label: "Statut",
                render: (l) => (
                  <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS[l.status]?.className ?? ""}`}>
                    {STATUS[l.status]?.label ?? l.status}
                  </span>
                ),
                searchValue: (l) => STATUS[l.status]?.label ?? l.status,
                sortValue: (l) => l.status,
              },
              { key: "message", label: "Détail", render: (l) => l.message ?? "" },
            ]}
          />
        </>
      )}
    </div>
  );
}
