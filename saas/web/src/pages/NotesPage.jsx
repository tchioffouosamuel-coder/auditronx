import { useCallback, useEffect, useRef, useState } from 'react'
import LoadingState from '../components/LoadingState'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'
import { notify } from '../lib/swal'

/** Messages de validation Laravel aplatis en une phrase. */
function messageErreur(error, defaut) {
  const erreurs = error.response?.data?.errors
  return erreurs ? Object.values(erreurs).flat().join(' ') : (error.response?.data?.message ?? defaut)
}

/**
 * Saisie des notes (§bulletins APC).
 *
 * **Un module à la fois.** Une classe de 40 élèves × 12 modules ne se saisit
 * pas d'un bloc : le tableau complet est ingérable au clavier et fragile sur
 * les connexions des établissements. L'écran s'ouvre donc sur l'avancement —
 * ce qu'il reste à remplir — et on entre dans un module pour le remplir.
 *
 * La grille se modifie en mémoire et s'enregistre en un envoi. La note finale
 * s'affiche en direct, calculée côté navigateur avec le même barème que le
 * serveur : c'est un confort de saisie, et le chiffre qui fait foi reste celui
 * que l'API renvoie après enregistrement.
 */
export default function NotesPage() {
  const [classes, setClasses] = useState([])
  const [annees, setAnnees] = useState([])
  const [contexte, setContexte] = useState({ classe_id: '', trimestre: 1, annee: '' })

  const [avancement, setAvancement] = useState(null)
  const [moduleCourant, setModuleCourant] = useState(null)
  const [grille, setGrille] = useState(null)
  const [lignes, setLignes] = useState([])
  const [modifie, setModifie] = useState(false)
  const [busy, setBusy] = useState(false)
  const fichierRef = useRef(null)

  useEffect(() => {
    fetchAllPages('/classes').then(setClasses)
    api.get('/eleves/annees').then(({ data }) => setAnnees(data))
  }, [])

  const params = useCallback(
    (extra = {}) => ({
      classe_id: contexte.classe_id,
      trimestre: contexte.trimestre,
      ...(contexte.annee ? { annee: contexte.annee } : {}),
      ...extra,
    }),
    [contexte],
  )

  const chargerAvancement = useCallback(async () => {
    if (!contexte.classe_id) {
      setAvancement(null)
      return
    }
    const { data } = await api.get('/notes/avancement', { params: params() })
    setAvancement(data)
  }, [contexte.classe_id, params])

  useEffect(() => {
    chargerAvancement()
    setModuleCourant(null)
    setGrille(null)
  }, [chargerAvancement])

  async function ouvrirModule(module) {
    setBusy(true)
    try {
      const { data } = await api.get('/notes', { params: params({ classe_module_id: module.classe_module_id }) })
      setModuleCourant(module)
      setGrille(data)
      setLignes(data.eleves)
      setModifie(false)
    } catch (error) {
      notify(messageErreur(error, 'Impossible d’ouvrir ce module.'), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  function definir(inscriptionId, champ, valeur) {
    setLignes((actuelles) =>
      actuelles.map((l) => (l.inscription_id === inscriptionId ? { ...l, [champ]: valeur } : l)),
    )
    setModifie(true)
  }

  async function enregistrer() {
    setBusy(true)
    try {
      const { data } = await api.put('/notes/bulk', {
        ...params({ classe_module_id: moduleCourant.classe_module_id }),
        notes: lignes.map((l) => ({
          inscription_id: l.inscription_id,
          moyenne_uea: l.moyenne_uea === '' ? null : l.moyenne_uea,
          note_integratrice: l.note_integratrice === '' ? null : l.note_integratrice,
        })),
      })
      setGrille(data)
      setLignes(data.eleves)
      setModifie(false)
      chargerAvancement()
      notify(`${data.bilan.enregistrees} note(s) enregistrée(s).`, { icon: 'success' })
    } catch (error) {
      notify(messageErreur(error, 'L’enregistrement a échoué.'), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function telechargerClasseur() {
    setBusy(true)
    try {
      const { data } = await api.get('/notes/classeur/export', { params: params(), responseType: 'blob' })
      const url = window.URL.createObjectURL(data)
      const lien = document.createElement('a')
      lien.href = url
      lien.download = `notes-T${contexte.trimestre}.xlsx`
      document.body.appendChild(lien)
      lien.click()
      lien.remove()
      window.URL.revokeObjectURL(url)
    } finally {
      setBusy(false)
    }
  }

  async function importerClasseur(fichier) {
    setBusy(true)
    try {
      const corps = new FormData()
      corps.append('file', fichier)
      corps.append('classe_id', contexte.classe_id)
      corps.append('trimestre', contexte.trimestre)
      if (contexte.annee) corps.append('annee', contexte.annee)

      const { data } = await api.post('/notes/classeur/import', corps, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      setAvancement(data.avancement)
      setModuleCourant(null)
      setGrille(null)

      const refusees = data.erreurs?.length ?? 0
      notify(
        `${data.lues} note(s) importée(s)${refusees ? `, ${refusees} ligne(s) refusée(s) : ${data.erreurs[0].erreur}` : '.'}`,
        { icon: refusees ? 'warning' : 'success' },
      )
    } catch (error) {
      notify(messageErreur(error, 'L’import a échoué.'), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  // Aperçu local de la note finale : la somme des deux parts, arrondie au
  // dixième comme le fait le barème côté serveur.
  function noteFinale(ligne) {
    const uea = ligne.moyenne_uea
    const integratrice = ligne.note_integratrice
    if (uea === null || uea === '' || integratrice === null || integratrice === '') return null
    return Math.round((Number(uea) + Number(integratrice)) * 10) / 10
  }

  /*
    La mention est recalculée pendant la frappe, à partir des bornes que
    l'API envoie avec la grille. Afficher celle du serveur la laisserait
    périmée dès la première correction, à côté d'une note qui a changé.
    Les bornes sont des bornes inférieures, parcourues de la plus haute à la
    plus basse — même règle que BaremeApc.
  */
  function mention(note, bornes) {
    if (note === null) return null
    const trouvee = Object.entries(bornes ?? {}).find(([, borne]) => note >= borne)
    return trouvee ? trouvee[0] : null
  }

  const trimestres = [1, 2, 3]

  return (
    <div>
      <h1 className="page-title mb-4">Saisie des notes</h1>

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Classe</span>
          <select
            value={contexte.classe_id}
            onChange={(e) => setContexte((c) => ({ ...c, classe_id: e.target.value }))}
            className="field w-56 py-2"
          >
            <option value="">Choisir…</option>
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.code} · {c.nom}
              </option>
            ))}
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Trimestre</span>
          <select
            value={contexte.trimestre}
            onChange={(e) => setContexte((c) => ({ ...c, trimestre: Number(e.target.value) }))}
            className="field w-32 py-2"
          >
            {trimestres.map((t) => (
              <option key={t} value={t}>
                Trimestre {t}
              </option>
            ))}
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Année</span>
          <select
            value={contexte.annee}
            onChange={(e) => setContexte((c) => ({ ...c, annee: e.target.value }))}
            className="field w-40 py-2"
          >
            <option value="">En cours</option>
            {annees.map((a) => (
              <option key={a} value={a}>
                {a}
              </option>
            ))}
          </select>
        </label>

        {contexte.classe_id && (
          <>
            <button type="button" disabled={busy} onClick={telechargerClasseur} className="btn-secondary">
              Classeur de saisie (XLSX)
            </button>
            <button type="button" disabled={busy} onClick={() => fichierRef.current?.click()} className="btn-secondary">
              Réimporter le classeur
            </button>
            <input
              ref={fichierRef}
              type="file"
              accept=".xlsx,.xls,.csv"
              onChange={(e) => {
                const fichier = e.target.files?.[0]
                e.target.value = ''
                if (fichier) importerClasseur(fichier)
              }}
              className="hidden"
            />
          </>
        )}
      </div>

      {!contexte.classe_id && (
        <div className="card p-6 text-sm text-ink-500">Choisissez une classe pour commencer.</div>
      )}

      {contexte.classe_id && !avancement && <LoadingState />}

      {avancement && !moduleCourant && (
        <>
          <div className="card mb-4 flex flex-wrap gap-6 p-4 text-sm">
            <span>
              <span className="text-ink-500">Effectif</span> <strong>{avancement.effectif}</strong>
            </span>
            <span>
              <span className="text-ink-500">Modules saisis</span>{' '}
              <strong>
                {avancement.modules_complets} / {avancement.modules.length}
              </strong>
            </span>
            {avancement.prete ? (
              <span className="text-brand-700">Saisie complète — la classe est prête pour le conseil.</span>
            ) : (
              <span className="text-amber-700">Saisie incomplète</span>
            )}
          </div>

          {avancement.modules.length === 0 ? (
            <div className="card p-6 text-sm text-ink-500">
              Aucun module programmé au trimestre {contexte.trimestre} dans la maquette de cette classe.
            </div>
          ) : (
            <div className="card overflow-x-auto p-0">
              <table className="w-full min-w-[44rem] text-sm">
                <thead className="bg-ink-50 text-left text-xs uppercase tracking-wide text-ink-500">
                  <tr>
                    <th className="px-3 py-2">Code</th>
                    <th className="px-3 py-2">Module</th>
                    <th className="px-3 py-2">Enseignant</th>
                    <th className="px-3 py-2">Crédit</th>
                    <th className="px-3 py-2">Avancement</th>
                    <th className="px-3 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {avancement.modules.map((module) => (
                    <tr key={module.classe_module_id} className="border-t border-ink-100">
                      <td className="px-3 py-2 font-medium">{module.code}</td>
                      <td className="px-3 py-2">{module.intitule}</td>
                      <td className="px-3 py-2">{module.enseignant ?? '—'}</td>
                      <td className="px-3 py-2">{module.credit ?? <span className="text-amber-700">à saisir</span>}</td>
                      <td className="px-3 py-2">
                        <span className={module.complet ? 'text-brand-700' : 'text-amber-700'}>
                          {module.saisies} / {module.attendus}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-right">
                        <button type="button" disabled={busy} onClick={() => ouvrirModule(module)} className="btn-ghost">
                          Saisir
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {grille && moduleCourant && (
        <>
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-base font-medium text-ink-900">
                {moduleCourant.code} · {moduleCourant.intitule}
              </h2>
              <p className="text-sm text-ink-500">
                Trimestre {grille.trimestre} · {grille.annee_scolaire} · seuil de réussite{' '}
                {grille.bareme.seuil_reussite} / 100
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => {
                  setModuleCourant(null)
                  setGrille(null)
                }}
                className="btn-ghost"
              >
                Retour aux modules
              </button>
              <button type="button" disabled={busy || !modifie} onClick={enregistrer} className="btn-primary">
                {busy ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </div>
          </div>

          {modifie && <p className="mb-2 text-sm text-amber-700">Modifications non enregistrées</p>}

          <div className="card overflow-x-auto p-0">
            <table className="w-full min-w-[44rem] text-sm">
              <thead className="bg-ink-50 text-left text-xs uppercase tracking-wide text-ink-500">
                <tr>
                  <th className="px-3 py-2">Matricule</th>
                  <th className="px-3 py-2">Nom</th>
                  <th className="w-28 px-3 py-2">UEA / {grille.bareme.uea}</th>
                  <th className="w-28 px-3 py-2">Intégratrice / {grille.bareme.integratrice}</th>
                  <th className="w-24 px-3 py-2">Note / 100</th>
                  <th className="px-3 py-2">Mention</th>
                </tr>
              </thead>
              <tbody>
                {lignes.map((ligne) => {
                  const note = noteFinale(ligne)
                  const sousSeuil = note !== null && note < grille.bareme.seuil_reussite

                  return (
                    <tr key={ligne.inscription_id} className="border-t border-ink-100">
                      <td className="px-3 py-2">{ligne.matricule}</td>
                      <td className="px-3 py-2">{ligne.nom_complet}</td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          min={0}
                          max={grille.bareme.uea}
                          step="0.5"
                          disabled={ligne.verrouillee}
                          value={ligne.moyenne_uea ?? ''}
                          onChange={(e) =>
                            definir(ligne.inscription_id, 'moyenne_uea', e.target.value === '' ? null : Number(e.target.value))
                          }
                          className="field w-24 py-1"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          min={0}
                          max={grille.bareme.integratrice}
                          step="0.5"
                          disabled={ligne.verrouillee}
                          value={ligne.note_integratrice ?? ''}
                          onChange={(e) =>
                            definir(
                              ligne.inscription_id,
                              'note_integratrice',
                              e.target.value === '' ? null : Number(e.target.value),
                            )
                          }
                          className="field w-24 py-1"
                        />
                      </td>
                      <td className={`px-3 py-2 font-medium ${sousSeuil ? 'text-red-700' : ''}`}>
                        {note ?? '—'}
                      </td>
                      <td className="px-3 py-2">
                        {ligne.verrouillee && (
                          <span className="mr-2 rounded bg-ink-100 px-1.5 py-0.5 text-xs text-ink-600">verrouillée</span>
                        )}
                        {note === null
                          ? '—'
                          : sousSeuil
                            ? grille.bareme.appreciation_non_acquise
                            : mention(note, grille.bareme.mentions) ?? ''}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
