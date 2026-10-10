import { useEffect, useMemo, useState } from 'react'
import api from '../lib/api'
import { downloadFile } from '../lib/download'
import { todayIso } from '../lib/datetime'
import LoadingState from './LoadingState'

/** Lundi de la semaine contenant `iso`, au format `YYYY-MM-DD`. */
function lundiDe(iso) {
  const jour = new Date(`${iso}T00:00:00`)
  const decalage = (jour.getDay() + 6) % 7
  jour.setDate(jour.getDate() - decalage)

  return jour.toISOString().slice(0, 10)
}

function decalerSemaine(iso, semaines) {
  const jour = new Date(`${iso}T00:00:00`)
  jour.setDate(jour.getDate() + semaines * 7)

  return lundiDe(jour.toISOString().slice(0, 10))
}

function formatJourMois(iso) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
  })
}

/**
 * Journal hebdomadaire des présences (§4.2) : une ligne par membre du
 * personnel, une colonne par jour du lundi au samedi.
 *
 * Complète le journal quotidien plutôt qu'il ne le remplace. Le quotidien ne
 * liste que les présences enregistrées, un absent n'y figure donc pas du tout.
 * Cette grille part du personnel attendu, ce qui rend les absences visibles.
 */
export default function JournalHebdomadaire() {
  const [semaine, setSemaine] = useState(() => lundiDe(todayIso()))
  const [donnees, setDonnees] = useState(null)
  const [loading, setLoading] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [recherche, setRecherche] = useState('')

  useEffect(() => {
    let annule = false
    setLoading(true)
    setErreur(null)

    api
      .get('/assiduite/journal-hebdomadaire', { params: { semaine } })
      .then(({ data }) => {
        if (!annule) setDonnees(data)
      })
      .catch(() => {
        if (!annule) setErreur('Impossible de charger le journal de la semaine.')
      })
      .finally(() => {
        if (!annule) setLoading(false)
      })

    return () => {
      annule = true
    }
  }, [semaine])

  const lignes = useMemo(() => {
    const toutes = donnees?.lignes ?? []
    const terme = recherche.trim().toLowerCase()

    if (!terme) return toutes

    return toutes.filter((ligne) =>
      [ligne.nom, ligne.matricule, ligne.section].some((valeur) =>
        String(valeur ?? '').toLowerCase().includes(terme),
      ),
    )
  }, [donnees, recherche])

  const jours = donnees?.jours ?? []

  const totalAbsences = useMemo(
    () =>
      lignes.reduce(
        (total, ligne) => total + (ligne.jours_attendus - ligne.jours_presents),
        0,
      ),
    [lignes],
  )

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => setSemaine((s) => decalerSemaine(s, -1))}
          className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm hover:bg-ink-50"
        >
          ← Semaine précédente
        </button>
        <input
          type="date"
          value={semaine}
          onChange={(e) => setSemaine(lundiDe(e.target.value))}
          className="rounded-md border border-ink-100 px-3 py-1.5 text-sm"
        />
        <button
          type="button"
          onClick={() => setSemaine((s) => decalerSemaine(s, 1))}
          className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm hover:bg-ink-50"
        >
          Semaine suivante →
        </button>
        <button
          type="button"
          onClick={() => setSemaine(lundiDe(todayIso()))}
          className="rounded-md border border-ink-100 bg-white px-3 py-1.5 text-sm hover:bg-ink-50"
        >
          Cette semaine
        </button>

        <button
          type="button"
          onClick={() =>
            downloadFile(
              `/assiduite/journal-hebdomadaire/pdf?semaine=${semaine}`,
              `journal-hebdomadaire-${semaine}.pdf`,
            )
          }
          className="ml-auto rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800"
        >
          Télécharger la semaine en PDF
        </button>
      </div>

      <div className="mb-3 flex flex-wrap items-center gap-3">
        <input
          type="search"
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
          placeholder="Nom, matricule, section…"
          className="w-full max-w-xs rounded-md border border-ink-100 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
        />
        {!loading && donnees && (
          <span className="text-xs text-ink-300">
            {lignes.length} membre{lignes.length > 1 ? 's' : ''} du personnel ·{' '}
            {totalAbsences} absence{totalAbsences > 1 ? 's' : ''} sur les jours attendus
          </span>
        )}
      </div>

      {erreur && (
        <div className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{erreur}</div>
      )}

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              <th className="sticky left-0 z-10 bg-ink-50 px-4 py-2.5 text-left font-medium text-ink-500">
                Personnel
              </th>
              <th className="px-4 py-2.5 text-left font-medium text-ink-500">Section</th>
              {jours.map((jour) => (
                <th
                  key={jour.date}
                  className="px-3 py-2.5 text-center font-medium text-ink-500"
                >
                  {jour.libelle}
                  <span className="block text-xs font-normal text-ink-300">
                    {jour.jour_mois ?? formatJourMois(jour.date)}
                  </span>
                </th>
              ))}
              <th className="px-3 py-2.5 text-center font-medium text-ink-500">Présences</th>
              <th className="px-3 py-2.5 text-center font-medium text-ink-500">Taux</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr>
                <td colSpan={jours.length + 4} className="px-4 py-5 text-center">
                  <LoadingState />
                </td>
              </tr>
            )}
            {!loading && lignes.length === 0 && (
              <tr>
                <td
                  colSpan={jours.length + 4}
                  className="px-4 py-8 text-center text-ink-300"
                >
                  {recherche
                    ? 'Aucun résultat pour cette recherche.'
                    : 'Aucun membre du personnel dans votre périmètre.'}
                </td>
              </tr>
            )}
            {!loading &&
              lignes.map((ligne) => (
                <tr key={ligne.enseignant_id} className="hover:bg-ink-50">
                  <td className="sticky left-0 z-10 bg-white px-4 py-2 whitespace-nowrap text-ink-900">
                    {ligne.nom}
                  </td>
                  <td className="px-4 py-2 whitespace-nowrap text-ink-700">
                    {ligne.section ?? '—'}
                  </td>
                  {ligne.jours.map((cellule) => (
                    <Cellule key={cellule.date} cellule={cellule} />
                  ))}
                  <td className="px-3 py-2 text-center whitespace-nowrap text-ink-700">
                    {ligne.jours_presents} / {ligne.jours_attendus}
                  </td>
                  <td className="px-3 py-2 text-center whitespace-nowrap">
                    {ligne.taux_assiduite === null ? (
                      <span
                        className="text-ink-300"
                        title="Aucun jour attendu : emploi du temps probablement absent"
                      >
                        —
                      </span>
                    ) : (
                      <span className={couleurTaux(ligne.taux_assiduite)}>
                        {ligne.taux_assiduite} %
                      </span>
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <p className="mt-3 text-xs text-ink-300">
        ABS : attendu selon l’emploi du temps, aucun pointage. · : non attendu ce jour-là.
        — : taux indisponible, aucun jour attendu sur la semaine.
      </p>
    </div>
  )
}

function couleurTaux(taux) {
  if (taux >= 75) return 'font-medium text-brand-700'
  if (taux >= 50) return 'font-medium text-amber-600'
  return 'font-medium text-red-600'
}

function Cellule({ cellule }) {
  if (!cellule.attendu) {
    return (
      <td
        className="bg-ink-50/60 px-3 py-2 text-center text-ink-300"
        title="Non attendu ce jour-là"
      >
        ·
      </td>
    )
  }

  if (!cellule.present) {
    return (
      <td
        className="bg-red-50 px-3 py-2 text-center text-xs font-semibold text-red-700"
        title="Attendu, aucun pointage enregistré"
      >
        ABS
      </td>
    )
  }

  return (
    <td className="px-3 py-2 text-center whitespace-nowrap text-ink-700">
      <span className="block">{cellule.heure_arrivee ?? '—'}</span>
      <span className="block text-xs text-ink-300">{cellule.heure_depart ?? '—'}</span>
    </td>
  )
}
