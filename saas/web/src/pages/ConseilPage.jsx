import { useCallback, useEffect, useState } from 'react'
import LoadingState from '../components/LoadingState'
import Modal from '../components/Modal'
import api from '../lib/api'
import { downloadFile } from '../lib/download'
import fetchAllPages from '../lib/fetchAllPages'
import swal, { confirmAction, notify } from '../lib/swal'

/** Messages de validation Laravel aplatis en une phrase. */
function messageErreur(error, defaut) {
  const erreurs = error.response?.data?.errors
  return erreurs ? Object.values(erreurs).flat().join(' ') : (error.response?.data?.message ?? defaut)
}

const PERIODES = [
  { valeur: '1', libelle: 'Trimestre 1' },
  { valeur: '2', libelle: 'Trimestre 2' },
  { valeur: '3', libelle: 'Trimestre 3' },
  { valeur: 'annuel', libelle: 'Bilan annuel' },
]

const STATUTS = {
  aucun: { libelle: 'Bulletins non calculés', classe: 'bg-ink-100 text-ink-600' },
  brouillon: { libelle: 'Brouillon', classe: 'bg-amber-100 text-amber-800' },
  valide: { libelle: 'Validé', classe: 'bg-brand-100 text-brand-800' },
}

const nombre = (valeur) => (valeur === null || valeur === undefined ? '—' : String(valeur).replace('.', ','))

/**
 * Conseil de classe (§bulletins APC, lot 5).
 *
 * La classe triée par rang, les décisions proposées par le barème,
 * modifiables, une observation par élève, et un bouton « Valider » qui fige
 * les bulletins de toute la classe et verrouille les notes qui ont servi.
 *
 * Le calcul se fait côté serveur, en une passe pour la classe entière : rang
 * et moyennes de classe en dépendent. L'écran ne recalcule rien — il montre
 * ce qui sera imprimé.
 */
export default function ConseilPage() {
  const [classes, setClasses] = useState([])
  const [annees, setAnnees] = useState([])
  const [contexte, setContexte] = useState({ classe_id: '', periode: '1', annee: '' })
  const [etat, setEtat] = useState(null)
  const [busy, setBusy] = useState(false)
  const [detail, setDetail] = useState(null)

  useEffect(() => {
    fetchAllPages('/classes').then(setClasses)
    api.get('/eleves/annees').then(({ data }) => setAnnees(data))
  }, [])

  const params = useCallback(
    () => ({
      classe_id: contexte.classe_id,
      periode: contexte.periode,
      ...(contexte.annee ? { annee: contexte.annee } : {}),
    }),
    [contexte],
  )

  const charger = useCallback(async () => {
    setEtat(null)
    if (!contexte.classe_id) return
    const { data } = await api.get('/conseil', { params: params() })
    setEtat(data)
  }, [contexte.classe_id, params])

  useEffect(() => {
    charger()
  }, [charger])

  async function action(chemin, corps = {}, succes) {
    setBusy(true)
    try {
      const { data } = await api.post(`/conseil/${chemin}`, { ...params(), ...corps })
      setEtat(data)
      if (succes) notify(succes, { icon: 'success' })
    } catch (error) {
      notify(messageErreur(error, 'L’opération a échoué.'), { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function valider() {
    const ok = await confirmAction(
      'Les bulletins de toute la classe seront figés et les notes qui ont servi verrouillées. ' +
        'Pour les modifier ensuite, il faudra rouvrir le conseil, avec un motif.',
      { title: 'Valider le conseil', confirmText: 'Valider' },
    )
    if (ok) action('valider', {}, 'Conseil validé : bulletins figés, notes verrouillées.')
  }

  async function rouvrir() {
    const { value: motif, isConfirmed } = await swal.fire({
      title: 'Rouvrir le conseil',
      text: 'La version signée est conservée ; le prochain calcul produira une nouvelle version. Motif :',
      input: 'text',
      inputValidator: (v) => (!v || v.trim().length < 3 ? 'Le motif est obligatoire.' : undefined),
      showCancelButton: true,
      confirmButtonText: 'Rouvrir',
      cancelButtonText: 'Annuler',
      reverseButtons: true,
    })
    if (isConfirmed) action('rouvrir', { motif }, 'Conseil rouvert : les notes sont déverrouillées.')
  }

  async function modifier(bulletin, valeurs) {
    try {
      const { data } = await api.put(`/bulletins/${bulletin.id}`, valeurs)
      setEtat((e) => ({
        ...e,
        eleves: e.eleves.map((el) => (el.bulletin?.id === bulletin.id ? { ...el, bulletin: data } : el)),
      }))
    } catch (error) {
      notify(messageErreur(error, 'Modification refusée.'), { icon: 'error' })
    }
  }

  /*
    Impression (lot 6). Le serveur imprime l'instantané du calcul : un
    brouillon sort barré « BROUILLON », pour relecture. L'archive ZIP ne
    prend que les classes validées, toutes classes confondues.
  */
  const suffixe = () => `${contexte.periode === 'annuel' ? 'annuel' : `T${contexte.periode}`}`
  const requete = () => new URLSearchParams({ periode: contexte.periode, ...(contexte.annee ? { annee: contexte.annee } : {}) })

  async function telecharger(url, nom) {
    setBusy(true)
    try {
      await downloadFile(url, nom)
    } catch (error) {
      // La réponse d'erreur arrive en Blob (responseType: 'blob') : il faut la relire.
      let message = 'Le téléchargement a échoué.'
      try {
        message = JSON.parse(await error.response?.data?.text())?.message ?? message
      } catch {
        // corps illisible : message par défaut
      }
      notify(message, { icon: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function ouvrirDetail(bulletin) {
    const { data } = await api.get(`/bulletins/${bulletin.id}`)
    setDetail(data)
  }

  const modifiable = etat?.statut === 'brouillon'
  const statut = STATUTS[etat?.statut] ?? STATUTS.aucun

  return (
    <div>
      <h1 className="page-title mb-4">Conseil de classe</h1>

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
          <span className="mb-1 block text-ink-700">Période</span>
          <select
            value={contexte.periode}
            onChange={(e) => setContexte((c) => ({ ...c, periode: e.target.value }))}
            className="field w-40 py-2"
          >
            {PERIODES.map((p) => (
              <option key={p.valeur} value={p.valeur}>
                {p.libelle}
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

        {etat && (
          <div className="flex flex-wrap gap-2">
            {etat.statut !== 'valide' && (
              <button type="button" disabled={busy} onClick={() => action('generer')} className="btn-secondary">
                {etat.statut === 'aucun' ? 'Calculer les bulletins' : 'Recalculer'}
              </button>
            )}
            {modifiable && (
              <button
                type="button"
                disabled={busy || etat.obstacles.length > 0}
                onClick={valider}
                className="btn-primary"
              >
                Valider le conseil
              </button>
            )}
            {etat.statut === 'valide' && (
              <button type="button" disabled={busy} onClick={rouvrir} className="btn-secondary">
                Rouvrir
              </button>
            )}
            {etat.statut !== 'aucun' && (
              <>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() =>
                    telecharger(
                      `/bulletins/classe/${contexte.classe_id}/pdf?${requete()}`,
                      `bulletins-${etat.classe.code}-${suffixe()}.pdf`,
                    )
                  }
                  className="btn-secondary"
                >
                  Bulletins (PDF)
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() =>
                    telecharger(
                      `/bulletins/classe/${contexte.classe_id}/pv?${requete()}`,
                      `pv-conseil-${etat.classe.code}-${suffixe()}.pdf`,
                    )
                  }
                  className="btn-secondary"
                >
                  PV du conseil
                </button>
              </>
            )}
            <button
              type="button"
              disabled={busy}
              title="Toutes les classes validées pour cette période, un dossier par classe"
              onClick={() => telecharger(`/bulletins/export-zip?${requete()}`, `bulletins-${suffixe()}.zip`)}
              className="btn-ghost"
            >
              Archive établissement (ZIP)
            </button>
          </div>
        )}
      </div>

      {!contexte.classe_id && (
        <div className="card p-6 text-sm text-ink-500">Choisissez une classe pour commencer.</div>
      )}

      {contexte.classe_id && !etat && <LoadingState />}

      {etat && (
        <>
          <div className="card mb-4 flex flex-wrap items-center gap-6 p-4 text-sm">
            <span className={`rounded px-2 py-0.5 text-xs font-medium ${statut.classe}`}>{statut.libelle}</span>
            <span>
              <span className="text-ink-500">Effectif</span> <strong>{etat.effectif}</strong>
              {etat.effectif_classe !== null && etat.effectif_classe !== etat.effectif && (
                <span className="text-ink-500"> ({etat.effectif_classe} classés)</span>
              )}
            </span>
            <span>
              <span className="text-ink-500">Moyenne de classe</span> <strong>{nombre(etat.moyenne_classe)}</strong>
            </span>
            <span>
              <span className="text-ink-500">Plus haute / plus basse</span>{' '}
              <strong>
                {nombre(etat.moyenne_max)} / {nombre(etat.moyenne_min)}
              </strong>
            </span>
            {etat.avancement.map((a) => (
              <span key={a.trimestre} className={a.prete ? 'text-brand-700' : 'text-amber-700'}>
                Saisie T{a.trimestre} {a.prete ? 'complète' : 'incomplète'}
              </span>
            ))}
            {etat.versions_remplacees > 0 && (
              <span className="text-ink-500">{etat.versions_remplacees} bulletin(s) d’une version antérieure conservé(s)</span>
            )}
          </div>

          {etat.statut === 'brouillon' && !etat.a_jour && (
            <p className="mb-2 text-sm text-amber-700">
              Les données ont changé depuis le calcul : recalculez avant de valider.
            </p>
          )}

          {etat.obstacles.length > 0 && etat.statut === 'brouillon' && (
            <div className="card mb-4 border-l-4 border-amber-400 p-4 text-sm text-amber-800">
              <p className="mb-1 font-medium">Validation impossible pour l’instant :</p>
              <ul className="list-disc pl-5">
                {etat.obstacles.map((o) => (
                  <li key={o}>{o}</li>
                ))}
              </ul>
            </div>
          )}

          <div className="card overflow-x-auto p-0">
            <table className="w-full min-w-[60rem] text-sm">
              <thead className="bg-ink-50 text-left text-xs uppercase tracking-wide text-ink-500">
                <tr>
                  <th className="px-3 py-2">Rang</th>
                  <th className="px-3 py-2">Élève</th>
                  <th className="px-3 py-2">Moyenne</th>
                  <th className="px-3 py-2">Mention</th>
                  <th className="px-3 py-2">Crédits</th>
                  <th className="px-3 py-2">Non validées</th>
                  <th className="px-3 py-2">Position</th>
                  <th className="px-3 py-2">Décision</th>
                  <th className="px-3 py-2">Observation</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {etat.eleves.map((eleve) => {
                  const b = eleve.bulletin
                  return (
                    <tr key={eleve.inscription_id} className="border-t border-ink-100 align-top">
                      <td className="px-3 py-2 font-medium">
                        {b?.rang ? `${b.rang}${b.rang === 1 ? 'er' : 'e'}${b.ex_aequo ? ' ex æquo' : ''}` : '—'}
                      </td>
                      <td className="px-3 py-2">
                        <div>{eleve.nom_complet}</div>
                        <div className="text-xs text-ink-500">{eleve.matricule}</div>
                      </td>
                      <td className="px-3 py-2">
                        {b ? nombre(b.moyenne_generale) : '—'}
                        {b?.notes_manquantes > 0 && (
                          <div className="text-xs text-amber-700">{b.notes_manquantes} note(s) manquante(s)</div>
                        )}
                      </td>
                      <td className="px-3 py-2">{b?.mention ?? '—'}</td>
                      <td className="px-3 py-2">{b?.total_credits ?? '—'}</td>
                      <td className="px-3 py-2">{b ? b.competences_non_validees : '—'}</td>
                      <td className="px-3 py-2">{b?.position_libelle ?? '—'}</td>
                      <td className="px-3 py-2">
                        {!b ? (
                          '—'
                        ) : modifiable ? (
                          <select
                            value={b.decision ?? ''}
                            onChange={(e) => modifier(b, { decision: e.target.value || null })}
                            className="field w-48 py-1"
                          >
                            <option value="">—</option>
                            {etat.decisions_possibles.map((d) => (
                              <option key={d.valeur} value={d.valeur}>
                                {d.libelle}
                                {d.valeur === b.decision_proposee ? ' (proposée)' : ''}
                              </option>
                            ))}
                          </select>
                        ) : (
                          b.decision_libelle ?? '—'
                        )}
                        {b?.decision_conseil && (
                          <div className="text-xs text-ink-500">modifiée par le conseil</div>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        {b && modifiable ? (
                          <input
                            key={`${b.id}-${b.observation ?? ''}`}
                            defaultValue={b.observation ?? ''}
                            onBlur={(e) => {
                              if (e.target.value !== (b.observation ?? '')) modifier(b, { observation: e.target.value })
                            }}
                            className="field w-56 py-1"
                          />
                        ) : (
                          (b?.observation ?? '')
                        )}
                      </td>
                      <td className="px-3 py-2 text-right">
                        {b && (
                          <div className="flex justify-end gap-1">
                            <button type="button" onClick={() => ouvrirDetail(b)} className="btn-ghost">
                              Détail
                            </button>
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => telecharger(`/bulletins/${b.id}/pdf`, `bulletin-${eleve.matricule}-${suffixe()}.pdf`)}
                              className="btn-ghost"
                            >
                              PDF
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </>
      )}

      {detail && <DetailBulletin bulletin={detail} onClose={() => setDetail(null)} />}
    </div>
  )
}

/** Les lignes d'un bulletin, telles qu'elles seront imprimées. */
function DetailBulletin({ bulletin, onClose }) {
  const annuel = bulletin.type === 'annuel'
  const trimestres = annuel
    ? Object.keys(bulletin.lignes[0]?.notes_trimestres ?? {})
    : []

  return (
    <Modal title={`${bulletin.eleve?.nom_complet ?? ''} · ${annuel ? 'Bilan annuel' : `Trimestre ${bulletin.trimestre}`}`} onClose={onClose}>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs uppercase tracking-wide text-ink-500">
            <tr>
              <th className="px-2 py-1">Code</th>
              <th className="px-2 py-1">Module</th>
              <th className="px-2 py-1">Crédit</th>
              {annuel ? (
                trimestres.map((t) => (
                  <th key={t} className="px-2 py-1">
                    T{t}
                  </th>
                ))
              ) : (
                <>
                  <th className="px-2 py-1">UEA</th>
                  <th className="px-2 py-1">Intégr.</th>
                </>
              )}
              <th className="px-2 py-1">Note</th>
              <th className="px-2 py-1">Mention</th>
              <th className="px-2 py-1">Sanction</th>
              <th className="px-2 py-1">Appréciation</th>
            </tr>
          </thead>
          <tbody>
            {bulletin.lignes.map((l) => (
              <tr key={l.id} className="border-t border-ink-100">
                <td className="px-2 py-1 font-medium">{l.code}</td>
                <td className="px-2 py-1">{l.intitule}</td>
                <td className="px-2 py-1">{l.hors_totaux ? `(${l.credit ?? ''})` : l.credit}</td>
                {annuel ? (
                  trimestres.map((t) => (
                    <td key={t} className="px-2 py-1">
                      {l.trimestres?.includes(Number(t)) ? nombre(l.notes_trimestres[t]) : '—'}
                    </td>
                  ))
                ) : (
                  <>
                    <td className="px-2 py-1">{l.hors_totaux ? '—' : nombre(l.moyenne_uea)}</td>
                    <td className="px-2 py-1">{l.hors_totaux ? '—' : nombre(l.note_integratrice)}</td>
                  </>
                )}
                <td className={`px-2 py-1 font-medium ${l.note_manquante ? 'text-amber-700' : ''}`}>
                  {l.note_manquante ? 'manquante' : nombre(l.note_finale)}
                </td>
                <td className="px-2 py-1">{l.mention ?? '—'}</td>
                <td className="px-2 py-1">{l.sanction ?? '—'}</td>
                <td className="px-2 py-1">{l.appreciation ?? ''}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="border-t-2 border-ink-200 font-medium">
              <td className="px-2 py-1" colSpan={2}>
                Total
              </td>
              <td className="px-2 py-1">{bulletin.total_credits}</td>
              <td className="px-2 py-1" colSpan={annuel ? trimestres.length : 2} />
              <td className="px-2 py-1">{nombre(bulletin.moyenne_generale)}</td>
              <td className="px-2 py-1">{bulletin.mention ?? '—'}</td>
              <td className="px-2 py-1" colSpan={2}>
                {bulletin.decision_libelle ?? ''}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </Modal>
  )
}
