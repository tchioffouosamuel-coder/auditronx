import { Fragment, useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import LoadingState from '../components/LoadingState'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'
import { confirmAction, notify } from '../lib/swal'

/** Messages de validation Laravel aplatis en une phrase. */
function messageErreur(error, defaut) {
  const erreurs = error.response?.data?.errors
  return erreurs ? Object.values(erreurs).flat().join(' ') : (error.response?.data?.message ?? defaut)
}

/**
 * Maquette d'une classe : les modules qu'elle suit, leurs crédits, leurs
 * heures, l'enseignant qui signe et les trimestres où chacun est programmé.
 *
 * La programmation par trimestre n'est pas un détail de confort : c'est elle
 * qui produit les tirets du bulletin annuel — un module non ouvert au premier
 * trimestre n'y a pas un zéro, il en sort — et c'est elle qui décide des
 * modules exclus des totaux, comme le stage de deuxième année dont le crédit
 * s'imprime entre parenthèses.
 *
 * Les lignes sont modifiées en mémoire puis enregistrées en un envoi : une
 * requête par case cochée rendrait l'écran inutilisable sur les connexions
 * que l'on trouve dans les établissements.
 */
export default function MaquettePage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [maquette, setMaquette] = useState(null)
  const [enseignants, setEnseignants] = useState([])
  const [lignes, setLignes] = useState([])
  const [modifie, setModifie] = useState(false)
  const [busy, setBusy] = useState(false)

  const charger = useCallback(async () => {
    const { data } = await api.get(`/classes/${id}/maquette`)
    setMaquette(data)
    setLignes(data.lignes)
    setModifie(false)
  }, [id])

  useEffect(() => {
    charger()
    fetchAllPages('/personnel').then(setEnseignants)
  }, [charger])

  function definir(moduleId, champ, valeur) {
    setLignes((actuelles) =>
      actuelles.map((l) => (l.module_id === moduleId ? { ...l, [champ]: valeur } : l)),
    )
    setModifie(true)
  }

  function basculerTrimestre(moduleId, trimestre) {
    setLignes((actuelles) =>
      actuelles.map((l) =>
        l.module_id === moduleId
          ? {
              ...l,
              trimestres: l.trimestres.includes(trimestre)
                ? l.trimestres.filter((t) => t !== trimestre)
                : [...l.trimestres, trimestre].sort(),
            }
          : l,
      ),
    )
    setModifie(true)
  }

  async function initialiser(rafraichir) {
    setBusy(true)
    try {
      const { data } = await api.post(`/classes/${id}/maquette/initialiser`, { rafraichir })
      setMaquette(data)
      setLignes(data.lignes)
      setModifie(false)
      notify(
        `${data.bilan.ajoutes} module(s) ajouté(s), ${data.bilan.completes} complété(s).`,
        { icon: 'success' },
      )
    } catch (error) {
      notify(messageErreur(error, "L'initialisation a échoué."), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function enregistrer() {
    setBusy(true)
    try {
      const { data } = await api.put(`/classes/${id}/maquette`, {
        lignes: lignes.map((l) => ({
          module_id: l.module_id,
          credit: l.credit === '' ? null : l.credit,
          volume_horaire: l.volume_horaire === '' ? null : l.volume_horaire,
          enseignant_id: l.enseignant_id || null,
          trimestres: l.trimestres,
          ordre: l.ordre,
        })),
      })
      setMaquette(data)
      setLignes(data.lignes)
      setModifie(false)
      notify('Maquette enregistrée.', { icon: 'success' })
    } catch (error) {
      notify(messageErreur(error, "L'enregistrement a échoué."), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function retirer(ligne) {
    const confirme = await confirmAction(
      `Retirer « ${ligne.code} · ${ligne.intitule} » de la maquette ?`,
      { confirmText: 'Retirer' },
    )
    if (!confirme) return

    await api.delete(`/classes/${id}/maquette/${ligne.module_id}`)
    charger()
  }

  if (!maquette) return <LoadingState />

  const trimestres = Array.from({ length: maquette.nombre_trimestres }, (_, i) => i + 1)
  const { classe, totaux } = maquette

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="page-title">Maquette · {classe.code}</h1>
          <p className="text-sm text-ink-500">
            {classe.nom}
            {classe.niveau && ` · ${classe.niveau}`}
            {classe.specialite && ` · ${classe.specialite.code} — ${classe.specialite.nom}`}
          </p>
        </div>
        <button type="button" onClick={() => navigate('/classes')} className="btn-ghost">
          Retour aux classes
        </button>
      </div>

      {maquette.obstacle && (
        <div className="card mb-4 border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <strong>Maquette indisponible :</strong> {maquette.obstacle}.
        </div>
      )}

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <button type="button" disabled={busy} onClick={() => initialiser(false)} className="btn-secondary">
          Reprendre le référentiel
        </button>
        <button type="button" disabled={busy} onClick={() => initialiser(true)} className="btn-secondary">
          Réaligner crédits et heures
        </button>
        <button type="button" disabled={busy || !modifie} onClick={enregistrer} className="btn-primary">
          {busy ? 'Enregistrement…' : 'Enregistrer'}
        </button>
        {modifie && <span className="text-sm text-amber-700">Modifications non enregistrées</span>}
      </div>

      <div className="card mb-4 flex flex-wrap gap-6 p-4 text-sm">
        <span>
          <span className="text-ink-500">Modules</span>{' '}
          <strong>{totaux.modules_programmes}</strong>
          {totaux.modules !== totaux.modules_programmes && (
            <span className="text-ink-500"> / {totaux.modules} (dont {totaux.modules - totaux.modules_programmes} non programmé(s))</span>
          )}
        </span>
        <span>
          <span className="text-ink-500">Crédits</span> <strong>{totaux.credits}</strong>
        </span>
        <span>
          <span className="text-ink-500">Volume horaire</span> <strong>{totaux.volume_horaire} h</strong>
        </span>
        {totaux.credits_manquants > 0 && (
          <span className="text-amber-700">
            {totaux.credits_manquants} module(s) sans crédit — la moyenne générale les ignorera
          </span>
        )}
      </div>

      {lignes.length === 0 ? (
        <div className="card p-6 text-sm text-ink-500">
          Aucun module. « Reprendre le référentiel » y verse ceux de la spécialité.
        </div>
      ) : (
        <div className="card overflow-x-auto p-0">
          <table className="w-full min-w-[56rem] text-sm">
            <thead className="bg-ink-50 text-left text-xs uppercase tracking-wide text-ink-500">
              <tr>
                <th className="px-3 py-2">Code</th>
                <th className="px-3 py-2">Intitulé</th>
                <th className="w-24 px-3 py-2">Crédit</th>
                <th className="w-24 px-3 py-2">VH</th>
                <th className="px-3 py-2">Enseignant</th>
                {trimestres.map((t) => (
                  <th key={t} className="px-2 py-2 text-center">
                    T{t}
                  </th>
                ))}
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {lignes.map((ligne, index) => {
                const nouveauBloc = ligne.bloc !== lignes[index - 1]?.bloc
                const nonProgramme = ligne.trimestres.length === 0

                return (
                  <Fragment key={ligne.module_id}>
                    {nouveauBloc && (
                      <tr>
                        <th
                          colSpan={6 + trimestres.length}
                          className="bg-brand-50 px-3 py-1 text-left text-xs font-semibold uppercase tracking-wide text-brand-800"
                        >
                          {ligne.bloc_libelle}
                        </th>
                      </tr>
                    )}
                    <tr className="border-t border-ink-100">
                      <td className="px-3 py-2 font-medium">{ligne.code}</td>
                      <td className={`px-3 py-2 ${nonProgramme ? 'text-ink-400' : ''}`}>
                        {ligne.intitule}
                        {nonProgramme && (
                          <span className="ml-2 rounded bg-ink-100 px-1.5 py-0.5 text-xs text-ink-600">
                            non programmé
                          </span>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          min={0}
                          value={ligne.credit ?? ''}
                          onChange={(e) =>
                            definir(ligne.module_id, 'credit', e.target.value === '' ? null : Number(e.target.value))
                          }
                          className={`field w-20 py-1 ${ligne.credit == null ? 'border-amber-300' : ''}`}
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          min={0}
                          value={ligne.volume_horaire ?? ''}
                          onChange={(e) =>
                            definir(
                              ligne.module_id,
                              'volume_horaire',
                              e.target.value === '' ? null : Number(e.target.value),
                            )
                          }
                          className="field w-20 py-1"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <select
                          value={ligne.enseignant_id ?? ''}
                          onChange={(e) =>
                            definir(
                              ligne.module_id,
                              'enseignant_id',
                              e.target.value === '' ? null : Number(e.target.value),
                            )
                          }
                          className="field w-56 py-1"
                        >
                          <option value="">—</option>
                          {enseignants.map((e) => (
                            <option key={e.id} value={e.id}>
                              {e.nom}
                            </option>
                          ))}
                        </select>
                      </td>
                      {trimestres.map((t) => (
                        <td key={t} className="px-2 py-2 text-center">
                          <input
                            type="checkbox"
                            checked={ligne.trimestres.includes(t)}
                            onChange={() => basculerTrimestre(ligne.module_id, t)}
                            aria-label={`${ligne.code} programmé au trimestre ${t}`}
                          />
                        </td>
                      ))}
                      <td className="px-3 py-2 text-right">
                        <button type="button" onClick={() => retirer(ligne)} className="btn-ghost text-red-600">
                          Retirer
                        </button>
                      </td>
                    </tr>
                  </Fragment>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
