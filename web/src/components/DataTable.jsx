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

  const totalPages = Math.max(1, Math.ceil(sorted.length / pageSize));
  const currentPage = Math.min(page, totalPages);
  const pageRows = sorted.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );

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
      <div className="mb-3 flex items-center justify-between gap-3">
        <input
          type="search"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setPage(1);
          }}
          placeholder={searchPlaceholder}
          className="w-full max-w-xs rounded-md border border-ink-100 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
        />
        {!loading && (
          <span className="shrink-0 text-xs text-ink-300">
            {sorted.length} résultat{sorted.length > 1 ? "s" : ""}
          </span>
        )}
      </div>

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              {columns.map((c) => (
                <th
                  key={c.key}
                  onClick={() => toggleSort(c)}
                  className={`whitespace-nowrap px-4 py-2.5 font-medium text-ink-500 ${
                    c.align === "right" ? "text-right" : "text-left"
                  } ${c.sortable === false ? "" : "cursor-pointer select-none hover:text-ink-700"}`}
                >
                  {c.label}
                  {c.sortable !== false &&
                    sort.key === c.key &&
                    (sort.dir === "asc" ? " ▲" : " ▼")}
                </th>
              ))}
              {renderActions && <th className="px-4 py-2.5">{actionsLabel}</th>}
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
                <td
                  colSpan={colSpan}
                  className="px-4 py-8 text-center text-ink-300"
                >
                  {query
                    ? "Aucun résultat pour cette recherche."
                    : emptyMessage}
                </td>
              </tr>
            )}
            {!loading &&
              pageRows.map((row, i) => (
                <tr
                  key={getRowKey ? getRowKey(row, i) : (row[idKey] ?? i)}
                  className="hover:bg-ink-50"
                >
                  {columns.map((c) => (
                    <td
                      key={c.key}
                      className={`whitespace-nowrap px-4 py-2.5 text-ink-700 ${c.align === "right" ? "text-right" : ""}`}
                    >
                      {c.render ? c.render(row) : String(row[c.key] ?? "—")}
                    </td>
                  ))}
                  {renderActions && (
                    <td className="whitespace-nowrap px-4 py-2.5 text-right">
                      {renderActions(row)}
                    </td>
                  )}
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {!loading && totalPages > 1 && (
        <div className="mt-3 flex items-center justify-between text-sm text-ink-500">
          <span>
            Page {currentPage} / {totalPages}
          </span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={currentPage === 1}
              className="rounded-md border border-ink-100 px-3 py-1 hover:bg-ink-50 disabled:opacity-40"
            >
              Précédent
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
              className="rounded-md border border-ink-100 px-3 py-1 hover:bg-ink-50 disabled:opacity-40"
            >
              Suivant
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
