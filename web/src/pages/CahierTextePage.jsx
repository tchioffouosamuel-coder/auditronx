import { useEffect, useState } from 'react'
import api from '../lib/api'

// Lecture seule : la saisie du cahier de texte se fait désormais depuis l'app
// mobile, par l'enseignant lui-même sur son propre créneau (l'admin ne fait
// que consulter l'historique).
export default function CahierTextePage() {
  const [enseignants, setEnseignants] = useState([])
  const [enseignantId, setEnseignantId] = useState('')
  const [entrees, setEntrees] = useState([])

  useEffect(() => {
    api.get('/personnel?per_page=500').then(({ data }) => setEnseignants(data.data ?? []))
  }, [])

  useEffect(() => {
    if (!enseignantId) return
    api.get(`/cahier-texte/${enseignantId}`).then(({ data }) => setEntrees(data.data ?? []))
  }, [enseignantId])

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Cahier de texte</h1>
      <p className="mb-4 text-sm text-ink-300">Consultation seule — les entrées sont saisies par les enseignants depuis l’application mobile.</p>

      <label className="mb-4 block max-w-xs text-sm">
        <span className="mb-1 block text-ink-700">Enseignant</span>
        <select value={enseignantId} onChange={(e) => setEnseignantId(e.target.value)} className="w-full rounded-md border border-ink-100 px-3 py-2">
          <option value="">Sélectionner…</option>
          {enseignants.map((e) => (
            <option key={e.id} value={e.id}>{e.nom}</option>
          ))}
        </select>
      </label>

      {enseignantId && (
        <div className="space-y-3">
          {entrees.length === 0 && (
            <p className="text-sm text-ink-300">Aucune entrée pour cet enseignant.</p>
          )}
          {entrees.map((entree) => (
            <div key={entree.id} className="rounded-lg border border-ink-100 bg-white p-4">
              <div className="mb-1 flex justify-between text-xs text-ink-300">
                <span>{entree.date}</span>
                <span>{entree.emploi_du_temps?.classe?.nom} — {entree.emploi_du_temps?.discipline?.nom}</span>
              </div>
              <p className="text-sm text-ink-900">{entree.contenu}</p>
              {entree.reference_programme && (
                <p className="mt-1 text-xs text-ink-300">Réf. programme : {entree.reference_programme}</p>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
