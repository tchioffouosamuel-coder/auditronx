import { useRef, useState } from 'react'
import api from '../lib/api'

/**
 * Import du classeur d'organisation pédagogique (référentiel APC).
 *
 * Distinct de SpreadsheetActions, pour deux raisons de fond :
 *
 * 1. **Un aperçu précède l'écriture.** Un classeur réécrit le référentiel de
 *    filières entières ; le découvrir après coup coûte cher. L'écran lit
 *    d'abord, montre ce qu'il a compris, et n'enregistre que sur
 *    confirmation.
 * 2. **Les anomalies ne sont pas toutes des erreurs.** Les classeurs réels
 *    portent des lignes à deux modules, des intitulés répétés, des codes
 *    absents et des totaux qui ne tombent pas juste. Ce qui bloque une ligne
 *    et ce qui mérite seulement un coup d'œil sont montrés séparément.
 */
function Lignes({ titre, entrees, champ, ton }) {
  if (!entrees?.length) return null

  const couleurs =
    ton === 'erreur'
      ? 'border-red-100 text-red-700'
      : 'border-amber-100 text-amber-800'

  return (
    <div className="mt-3">
      <p className={`font-medium ${ton === 'erreur' ? 'text-red-700' : 'text-amber-800'}`}>
        {entrees.length} {titre}
      </p>
      <ul className="mt-1 max-h-64 space-y-2 overflow-y-auto">
        {entrees.map((entree, i) => (
          <li key={i} className={`rounded-md border bg-white px-3 py-2 ${couleurs}`}>
            <div className="text-xs font-semibold uppercase tracking-wide text-ink-500">
              Onglet {entree.feuille} · Ligne {entree.ligne}
            </div>
            <div>{entree[champ]}</div>
            {entree.valeurs && Object.keys(entree.valeurs).length > 0 && (
              <div className="mt-1 flex flex-wrap gap-1">
                {Object.values(entree.valeurs).map((valeur, j) => (
                  <span key={j} className="rounded bg-ink-50 px-1.5 py-0.5 text-xs text-ink-700">
                    {String(valeur)}
                  </span>
                ))}
              </div>
            )}
          </li>
        ))}
      </ul>
    </div>
  )
}

function Diagnostic({ resultat }) {
  if (!resultat) return null

  if (resultat.erreur) {
    return <div className="rounded-md bg-red-50 px-3 py-2 text-red-700">{resultat.erreur}</div>
  }

  const ecarts = (resultat.controles ?? []).filter((c) => c.ecart !== 0)

  return (
    <div className="w-full space-y-3 text-sm">
      <div className="rounded-md bg-brand-50 px-3 py-2 text-brand-800">
        <p className="font-medium">
          {resultat.applique
            ? `${resultat.bilan.modules_crees} module(s) créé(s), ${resultat.bilan.modules_mis_a_jour} mis à jour.`
            : 'Aperçu — rien n’a encore été enregistré.'}
        </p>

        {resultat.specialites.length === 0 ? (
          <p className="mt-1">Aucune spécialité lisible dans ce classeur.</p>
        ) : (
          <table className="mt-2 w-full text-left text-xs">
            <thead className="text-ink-500">
              <tr>
                <th className="py-1 pr-3">Onglet</th>
                <th className="py-1 pr-3">Niveau</th>
                <th className="py-1 pr-3">Modules</th>
                <th className="py-1 pr-3">Heures</th>
                <th className="py-1 pr-3">Codes fabriqués</th>
                <th className="py-1">VH à compléter</th>
              </tr>
            </thead>
            <tbody>
              {resultat.specialites.map((s) => (
                <tr key={s.code} className="border-t border-brand-100">
                  <td className="py-1 pr-3 font-medium">{s.code}</td>
                  <td className="py-1 pr-3">{s.niveau}</td>
                  <td className="py-1 pr-3">{s.modules}</td>
                  <td className="py-1 pr-3">{s.total_volume_horaire} h</td>
                  <td className="py-1 pr-3">{s.codes_fabriques || '—'}</td>
                  <td className="py-1">{s.volumes_horaires_manquants || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {ecarts.length > 0 && (
        <div className="rounded-md bg-amber-50 px-3 py-2 text-amber-800">
          <p className="font-medium">Écarts avec l’onglet « Contrôle »</p>
          <ul className="mt-1 space-y-0.5">
            {ecarts.map((c) => (
              <li key={c.specialite}>
                {c.specialite} : {c.total_calcule} h dans les lignes, {c.total_annonce} h annoncées
                {' '}(<strong>{c.ecart > 0 ? '+' : ''}{c.ecart} h</strong>)
              </li>
            ))}
          </ul>
        </div>
      )}

      <Lignes titre="ligne(s) rejetée(s)" entrees={resultat.erreurs} champ="erreur" ton="erreur" />
      <Lignes titre="point(s) à vérifier" entrees={resultat.avertissements} champ="avertissement" ton="avertissement" />
    </div>
  )
}

export default function ReferentielApcImport({ onImported }) {
  const fileInputRef = useRef(null)
  const [fichier, setFichier] = useState(null)
  const [niveau, setNiveau] = useState('')
  const [busy, setBusy] = useState(false)
  const [resultat, setResultat] = useState(null)

  async function telecharger(chemin, nom) {
    setBusy(true)
    try {
      const { data } = await api.get(chemin, { responseType: 'blob' })
      const url = window.URL.createObjectURL(data)
      const lien = document.createElement('a')
      lien.href = url
      lien.download = nom
      document.body.appendChild(lien)
      lien.click()
      lien.remove()
      window.URL.revokeObjectURL(url)
    } finally {
      setBusy(false)
    }
  }

  async function envoyer(apercu) {
    if (!fichier) return

    setBusy(true)
    try {
      const corps = new FormData()
      corps.append('file', fichier)
      if (niveau) corps.append('niveau', niveau)
      if (apercu) corps.append('apercu', '1')

      const { data } = await api.post('/spreadsheet/referentiel/import', corps, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setResultat(data)

      if (!apercu) {
        setFichier(null)
        onImported?.()
      }
    } catch (err) {
      setResultat({ erreur: err.response?.data?.message ?? "Échec de l'import." })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="card mb-4 p-4 sm:p-6">
      <h2 className="text-sm font-medium text-ink-900">Importer le référentiel APC</h2>
      <p className="mt-1 mb-4 text-sm text-ink-500">
        Le classeur de l’organisation pédagogique : un onglet par spécialité, les modules rangés dans la colonne de
        leur bloc, le volume horaire en regard. Le niveau est lu dans la ligne « TOTAL … (h) » de chaque onglet ;
        le champ ci-dessous ne sert qu’aux classeurs qui n’en portent pas. Rien n’est supprimé : un module absent du
        fichier reste en place.
      </p>

      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          disabled={busy}
          onClick={() => telecharger('/spreadsheet/referentiel/template', 'referentiel-apc-modele.xlsx')}
          className="btn-secondary"
        >
          Télécharger le modèle
        </button>
        <button
          type="button"
          disabled={busy}
          onClick={() => telecharger('/spreadsheet/referentiel/export', 'referentiel-apc-export.xlsx')}
          className="btn-secondary"
        >
          Exporter en XLSX
        </button>

        <label className="text-sm">
          <input
            type="text"
            placeholder="Niveau (si absent du classeur)"
            value={niveau}
            onChange={(e) => setNiveau(e.target.value)}
            className="field w-56 py-2"
          />
        </label>

        <button type="button" disabled={busy} onClick={() => fileInputRef.current?.click()} className="btn-secondary">
          {fichier ? `Fichier : ${fichier.name}` : 'Choisir un classeur…'}
        </button>
        <input
          ref={fileInputRef}
          type="file"
          accept=".xlsx,.xls,.csv"
          onChange={(e) => {
            setFichier(e.target.files?.[0] ?? null)
            setResultat(null)
            e.target.value = ''
          }}
          className="hidden"
        />

        <button type="button" disabled={busy || !fichier} onClick={() => envoyer(true)} className="btn-secondary">
          Analyser
        </button>
        <button type="button" disabled={busy || !fichier} onClick={() => envoyer(false)} className="btn-primary">
          Importer
        </button>
      </div>

      {resultat && <div className="mt-4">
        <Diagnostic resultat={resultat} />
      </div>}
    </div>
  )
}
