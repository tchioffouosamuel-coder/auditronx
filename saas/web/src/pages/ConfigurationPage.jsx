import { useEffect, useState } from 'react'
import LoadingState from '../components/LoadingState'
import api from '../lib/api'
import { notify } from '../lib/swal'

// Jours ISO, comme côté API (1 = lundi ... 7 = dimanche).
const JOURS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
]

export default function ConfigurationPage() {
  const [jours, setJours] = useState([1, 2, 3, 4, 5])
  const [heureDebut, setHeureDebut] = useState('07:30')
  const [heureFin, setHeureFin] = useState('15:30')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    api.get('/parametres/horaires-administratifs').then(({ data }) => {
      setJours(data.jours)
      setHeureDebut(data.heure_debut)
      setHeureFin(data.heure_fin)
      setLoading(false)
    })
  }, [])

  function toggleJour(jour) {
    setJours((actuels) => (actuels.includes(jour) ? actuels.filter((j) => j !== jour) : [...actuels, jour].sort()))
  }

  async function save(e) {
    e.preventDefault()
    setSaving(true)
    try {
      await api.put('/parametres/horaires-administratifs', { jours, heure_debut: heureDebut, heure_fin: heureFin })
      notify('Horaires du personnel administratif enregistrés.', { icon: 'success' })
    } catch (error) {
      const erreurs = error.response?.data?.errors
      notify(erreurs ? Object.values(erreurs).flat().join(' ') : "L'enregistrement a échoué.", { icon: 'error' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <h1 className="page-title mb-4">Configuration</h1>

      {loading ? (
        <LoadingState />
      ) : (
        <form onSubmit={save} className="max-w-2xl card p-4 sm:p-6">
          <h2 className="text-sm font-medium text-ink-900">Horaires du personnel administratif</h2>
          <p className="mt-1 mb-4 text-sm text-ink-500">
            Le personnel de la section « Administration » n’est pas évalué selon un emploi du temps : il est attendu
            chaque jour coché ci-dessous, sur la plage horaire indiquée. Présences, absences, retards et départs
            anticipés sont calculés à partir de ces valeurs.
          </p>

          <fieldset className="mb-4">
            <legend className="mb-2 text-sm text-ink-700">Jours de présence</legend>
            <div className="flex flex-wrap gap-x-4 gap-y-2">
              {JOURS.map((jour) => (
                <label key={jour.value} className="flex items-center gap-2 text-sm text-ink-700">
                  <input type="checkbox" checked={jours.includes(jour.value)} onChange={() => toggleJour(jour.value)} />
                  {jour.label}
                </label>
              ))}
            </div>
          </fieldset>

          <div className="mb-4 flex flex-wrap gap-4">
            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Début de journée</span>
              <input
                type="time"
                required
                value={heureDebut}
                onChange={(e) => setHeureDebut(e.target.value)}
                className="field py-2"
              />
            </label>
            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Fin de journée</span>
              <input
                type="time"
                required
                value={heureFin}
                onChange={(e) => setHeureFin(e.target.value)}
                className="field py-2"
              />
            </label>
          </div>

          <button
            type="submit"
            disabled={saving || jours.length === 0}
            className="btn-primary"
          >
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </form>
      )}
    </div>
  )
}
