import { useMemo, useState } from "react";
import LoadingState from "./LoadingState";

/**
 * Table générique avec recherche, tri par colonne et pagination côté client —
 * remplace les `<table>` écrits à la main dans les pages de gestion et de
 * rapports. Volontairement client-side (le jeu de données affiché tient déjà
 * en mémoire côté appelant) : pas de round-trip serveur supplémentaire.
 *
 * `columns`: [{ key, label, render?(row), searchValue?(row), sortValue?(row),
 *               sortable? = true, align? = 'left'|'right' }]
 */
export default function DataTable({
  columns,
  rows,
  idKey = "id",
  getRowKey,
  loading = false,
  emptyMessage = "Aucune donnée.",
  searchPlaceholder = "Rechercher…",
  pageSize = 10,
  renderActions,
  actionsLabel = "",
}) {
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState({ key: null, dir: "asc" });
  const [page, setPage] = useState(1);
  const [taillePage, setTaillePage] = useState(pageSize);

  function cellText(column, row) {
    if (column.searchValue) return String(column.searchValue(row) ?? "");
    if (column.render) {
      const rendered = column.render(row);
      return typeof rendered === "string" || typeof rendered === "number"
        ? String(rendered)
        : "";
    }
    return String(row[column.key] ?? "");
  }

  function sortKeyOf(column, row) {
    if (column.sortValue) return column.sortValue(row);
    return cellText(column, row);
  }

  const filtered = useMemo(() => {
    if (!query.trim()) return rows;
    const q = query.trim().toLowerCase();
    return rows.filter((row) =>
      columns.some((c) => cellText(c, row).toLowerCase().includes(q)),
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, query, columns]);

  const sorted = useMemo(() => {
    if (!sort.key) return filtered;
    const column = columns.find((c) => c.key === sort.key);
    if (!column) return filtered;

    const withKeys = filtered.map((row) => ({
      row,
      k: sortKeyOf(column, row),
    }));
    withKeys.sort((a, b) => {
      const na = Number(a.k);
      const nb = Number(b.k);
      const bothNumeric =
        a.k !== "" && b.k !== "" && !Number.isNaN(na) && !Number.isNaN(nb);
      const cmp = bothNumeric
        ? na - nb
        : String(a.k).localeCompare(String(b.k), "fr");
      return sort.dir === "asc" ? cmp : -cmp;
    });
    return withKeys.map((w) => w.row);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filtered, sort, columns]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / taillePage));
  const currentPage = Math.min(page, totalPages);
  const premierIndex = (currentPage - 1) * taillePage;
  const pageRows = sorted.slice(premierIndex, premierIndex + taillePage);

  function toggleSort(column) {
    if (column.sortable === false) return;
    setPage(1);
    setSort((s) =>
      s.key === column.key
        ? { key: column.key, dir: s.dir === "asc" ? "desc" : "asc" }
        : { key: column.key, dir: "asc" },
    );
  }

  const colSpan = columns.length + (renderActions ? 1 : 0);

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div className="relative w-full max-w-xs">
          <span
            aria-hidden="true"
            className="material-symbols-rounded absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-ink-300"
          >
            search
          </span>
          <input
            type="search"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(1);
            }}
            placeholder={searchPlaceholder}
            aria-label={searchPlaceholder}
            className="field w-full py-2 pl-9"
          />
        </div>
        {!loading && (
          <span
            aria-live="polite"
            className="shrink-0 text-xs font-medium text-ink-400"
          >
            {sorted.length} résultat{sorted.length > 1 ? "s" : ""}
            {query && sorted.length !== rows.length && ` sur ${rows.length}`}
          </span>
        )}
      </div>

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-ink-100 text-sm">
            {/*
              En-tête collant : ces tableaux dépassent souvent la hauteur de
              l'écran (listes de personnel, rapports d'assiduité) et on perdait
              le nom des colonnes dès le premier défilement.
            */}
            <thead className="sticky top-0 z-10 bg-brand-50/95 backdrop-blur-sm">
              <tr>
                {columns.map((c) => {
                  const triable = c.sortable !== false;
                  const actif = sort.key === c.key;

                  return (
                    <th
                      key={c.key}
                      scope="col"
                      aria-sort={
                        actif
                          ? sort.dir === "asc"
                            ? "ascending"
                            : "descending"
                          : triable
                            ? "none"
                            : undefined
                      }
                      className={`px-4 py-2.5 text-xs font-bold tracking-wide whitespace-nowrap text-ink-500 uppercase ${
                        c.align === "right" ? "text-right" : "text-left"
                      }`}
                    >
                      {/*
                        Le tri vivait sur un `onClick` posé sur le `<th>` :
                        inatteignable au clavier et muet pour les lecteurs
                        d'écran. Un vrai bouton + `aria-sort` rend la colonne
                        utilisable sans souris.
                      */}
                      {triable ? (
                        <button
                          type="button"
                          onClick={() => toggleSort(c)}
                          className={`group inline-flex items-center gap-1 rounded transition hover:text-brand-700 ${
                            c.align === "right" ? "flex-row-reverse" : ""
                          } ${actif ? "text-brand-800" : ""}`}
                        >
                          <span>{c.label}</span>
                          <span
                            aria-hidden="true"
                            className={`material-symbols-rounded text-[16px] transition ${
                              actif
                                ? "text-brand-600 opacity-100"
                                : "opacity-0 group-hover:opacity-60"
                            }`}
                          >
                            {actif && sort.dir === "desc"
                              ? "arrow_downward"
                              : "arrow_upward"}
                          </span>
                        </button>
                      ) : (
                        c.label
                      )}
                    </th>
                  );
                })}
                {renderActions && (
                  <th
                    scope="col"
                    className="px-4 py-2.5 text-right text-xs font-bold tracking-wide text-ink-500 uppercase"
                  >
                    {actionsLabel}
                  </th>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-100">
              {loading && (
                <tr>
                  <td colSpan={colSpan} className="px-4 py-5 text-center">
                    <LoadingState />
                  </td>
                </tr>
              )}
              {!loading && pageRows.length === 0 && (
                <tr>
                  <td colSpan={colSpan} className="px-4 py-12 text-center">
                    <span
                      aria-hidden="true"
                      className="material-symbols-rounded mb-1 text-[32px] text-ink-200"
                    >
                      {query ? "search_off" : "inbox"}
                    </span>
                    <p className="text-sm font-medium text-ink-500">
                      {query
                        ? "Aucun résultat pour cette recherche."
                        : emptyMessage}
                    </p>
                    {query && (
                      <button
                        type="button"
                        onClick={() => setQuery("")}
                        className="btn-ghost mt-2 text-xs"
                      >
                        Effacer la recherche
                      </button>
                    )}
                  </td>
                </tr>
              )}
              {!loading &&
                pageRows.map((row, i) => (
                  <tr
                    key={getRowKey ? getRowKey(row, i) : (row[idKey] ?? i)}
                    className="transition-colors hover:bg-brand-50/60"
                  >
                    {columns.map((c) => (
                      <td
                        key={c.key}
                        className={`px-4 py-2.5 whitespace-nowrap text-ink-700 ${c.align === "right" ? "text-right" : ""}`}
                      >
                        {c.render ? c.render(row) : String(row[c.key] ?? "—")}
                      </td>
                    ))}
                    {renderActions && (
                      <td className="px-4 py-2.5 text-right whitespace-nowrap">
                        {renderActions(row)}
                      </td>
                    )}
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </div>

      {!loading && sorted.length > 0 && (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm text-ink-500">
          {/* « 11–20 sur 134 » plutôt que « Page 2 / 14 » : on situe les lignes
              affichées, pas seulement la page. */}
          <span className="tabular-nums">
            {premierIndex + 1}–{premierIndex + pageRows.length} sur{" "}
            {sorted.length}
          </span>

          <div className="flex items-center gap-3">
            <label className="flex items-center gap-1.5 text-xs">
              <span className="text-ink-400">Lignes</span>
              <select
                value={taillePage}
                onChange={(e) => {
                  setTaillePage(Number(e.target.value));
                  setPage(1);
                }}
                className="rounded-lg border border-brand-200 bg-white px-1.5 py-1 text-xs font-medium text-ink-700"
              >
                {[10, 25, 50, 100].map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </label>

            {totalPages > 1 && (
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                  aria-label="Page précédente"
                  className="btn-secondary px-2 py-1.5"
                >
                  <span
                    aria-hidden="true"
                    className="material-symbols-rounded text-[18px]"
                  >
                    chevron_left
                  </span>
                </button>
                <span className="px-1 text-xs font-semibold tabular-nums text-ink-600">
                  {currentPage} / {totalPages}
                </span>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                  aria-label="Page suivante"
                  className="btn-secondary px-2 py-1.5"
                >
                  <span
                    aria-hidden="true"
                    className="material-symbols-rounded text-[18px]"
                  >
                    chevron_right
                  </span>
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
