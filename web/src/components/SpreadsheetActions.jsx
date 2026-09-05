import { useRef, useState } from 'react'
import api from '../lib/api'

/**
 * Barre "Modèle / Exporter / Importer" en XLSX pour une entité principale
 * (§4.2 : personnel, classes, disciplines, emplois — voir SpreadsheetController
 * côté API). Les téléchargements passent par axios (pas un simple <a href>)
 * car l'API est authentifiée par Bearer token, jamais par cookie de session.
 */
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
                <ul className="mt-1 list-disc pl-5 text-red-700">
                  {result.erreurs.map((e, i) => (
                    <li key={i}>
                      Ligne {e.ligne} : {e.erreur}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
