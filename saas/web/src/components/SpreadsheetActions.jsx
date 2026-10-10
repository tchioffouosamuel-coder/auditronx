import { useRef, useState } from 'react'
import api from '../lib/api'

/**
 * Barre "Modèle / Exporter / Importer" en XLSX pour une entité principale
 * (§4.2 : personnel, classes, disciplines, emplois — voir SpreadsheetController
 * côté API). Les téléchargements passent par axios (pas un simple <a href>)
 * car l'API est authentifiée par Bearer token, jamais par cookie de session.
 */
// Regroupe les erreurs par feuille puis par ligne pour faciliter la correction du fichier.
function sortErreurs(erreurs) {
  return [...erreurs].sort((a, b) => (a.feuille ?? 0) - (b.feuille ?? 0) || a.ligne - b.ligne)
}

export default function SpreadsheetActions({ entity, label, onImported }) {
  const fileInputRef = useRef(null)
  const [busy, setBusy] = useState(false)
  const [result, setResult] = useState(null)

  async function downloadFile(path, filename) {
    setBusy(true)
    try {
      const { data } = await api.get(path, { responseType: 'blob' })
      const url = window.URL.createObjectURL(data)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } finally {
      setBusy(false)
    }
  }

  async function handleFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return

    setBusy(true)
    setResult(null)
    try {
      const form = new FormData()
      form.append('file', file)
      const { data } = await api.post(`/spreadsheet/${entity}/import`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setResult(data)
      onImported?.()
    } catch (err) {
      setResult({ erreur: err.response?.data?.message ?? "Échec de l'import." })
    } finally {
      setBusy(false)
      e.target.value = ''
    }
  }

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      <button
        type="button"
        disabled={busy}
        onClick={() => downloadFile(`/spreadsheet/${entity}/template`, `${entity}-modele.xlsx`)}
        className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-50 disabled:opacity-50"
      >
        Télécharger le modèle
      </button>
      <button
        type="button"
        disabled={busy}
        onClick={() => downloadFile(`/spreadsheet/${entity}/export`, `${entity}-export.xlsx`)}
        className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-50 disabled:opacity-50"
      >
        Exporter en XLSX
      </button>
      <button
        type="button"
        disabled={busy}
        onClick={() => fileInputRef.current?.click()}
        className="rounded-md border border-brand-700 bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800 disabled:opacity-50"
      >
        Importer un fichier {label ?? entity} (XLSX)
      </button>
      <input
        ref={fileInputRef}
        type="file"
        accept=".xlsx,.xls,.csv"
        onChange={handleFileChange}
        className="hidden"
      />

      {result && (
        <div className="w-full text-sm">
          {result.erreur ? (
            <div className="rounded-md bg-red-50 px-3 py-2 text-red-700">{result.erreur}</div>
          ) : (
            <div className="rounded-md bg-brand-50 px-3 py-2 text-brand-800">
              {result.importes} ligne(s) importée(s).
              {result.erreurs?.length > 0 && (
                <>
                  <p className="mt-2 font-medium text-red-700">
                    {result.erreurs.length} ligne(s) en échec :
                  </p>
                  <ul className="mt-1 space-y-2">
                    {sortErreurs(result.erreurs).map((e, i) => (
                      <li key={i} className="rounded-md border border-red-100 bg-white px-3 py-2">
                        <div className="text-xs font-semibold uppercase tracking-wide text-ink-500">
                          {e.feuille != null && `Feuille ${e.feuille_nom ?? e.feuille} · `}Ligne {e.ligne}
                        </div>
                        <div className="text-red-700">{e.erreur}</div>
                        {e.valeurs && Object.keys(e.valeurs).length > 0 && (
                          <div className="mt-1 flex flex-wrap gap-1">
                            {Object.entries(e.valeurs).map(([col, val]) => (
                              <span key={col} className="rounded bg-ink-50 px-1.5 py-0.5 text-xs text-ink-700">
                                <span className="text-ink-500">{col} :</span> {String(val)}
                              </span>
                            ))}
                          </div>
                        )}
                      </li>
                    ))}
                  </ul>
                </>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
