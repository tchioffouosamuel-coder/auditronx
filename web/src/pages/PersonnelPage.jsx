import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import api from '../lib/api'

export default function PersonnelPage() {
  const [importOpen, setImportOpen] = useState(false)
  const [importJson, setImportJson] = useState('[\n  { "nom": "", "matricule": "" }\n]')
  const [importResult, setImportResult] = useState(null)

  async function handleImport(e) {
    e.preventDefault()
    setImportResult(null)
    try {
      const enseignants = JSON.parse(importJson)
      const { data } = await api.post('/personnel/import', { enseignants })
      setImportResult(data)
    } catch (err) {
      setImportResult({ erreur: err.message })
    }
  }

  return (
    <div>
      <div className="mb-4 flex justify-end">
        <button
          onClick={() => setImportOpen((v) => !v)}
          className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-50"
        >
          Import en masse (JSON)
        </button>
      </div>

      {importOpen && (
        <form onSubmit={handleImport} className="mb-6 rounded-lg border border-ink-100 bg-white p-4">
          <textarea
            value={importJson}
            onChange={(e) => setImportJson(e.target.value)}
            rows={6}
            className="mb-2 w-full rounded-md border border-ink-100 p-2 font-mono text-xs"
          />
          <button type="submit" className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white">
            Importer
          </button>
          {importResult && (
            <pre className="mt-2 max-h-40 overflow-auto rounded-md bg-ink-50 p-2 text-xs">
              {JSON.stringify(importResult, null, 2)}
            </pre>
          )}
        </form>
      )}

      <ResourceTable
        title="Personnel"
        resource="/personnel"
        fields={[
          { key: 'nom', label: 'Nom', required: true },
          { key: 'matricule', label: 'Matricule', required: true },
          { key: 'email', label: 'Email', type: 'email' },
          { key: 'fonction', label: 'Fonction' },
          { key: 'section', label: 'Section' },
          { key: 'grade', label: 'Grade' },
          { key: 'tel', label: 'Téléphone (identifiant de connexion app mobile)' },
          { key: 'poste', label: 'Poste' },
          { key: 'password', label: 'Mot de passe app mobile (laisser vide pour ne pas changer)', type: 'password' },
          { key: 'est_admin', label: 'Accès direct à l’app sans OTP (admin)', type: 'checkbox' },
        ]}
      />
    </div>
  )
}
