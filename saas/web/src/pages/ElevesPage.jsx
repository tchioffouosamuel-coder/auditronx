import { useEffect, useMemo, useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'

/**
 * Élèves et inscriptions (§bulletins APC).
 *
 * Deux onglets parce que ce sont deux choses : l'**identité** d'un élève ne
 * change pas d'une année à l'autre, son **inscription** change chaque année —
 * classe, statut régulier ou redoublant. C'est l'inscription que désigneront
 * les notes et les bulletins, et c'est elle qui permet à un redoublant de
 * garder son année précédente intacte.
 *
 * La voie normale reste l'import : les listes de classe arrivent en classeur,
 * et une seule feuille y porte l'identité et l'inscription ensemble.
 */
export default function ElevesPage() {
  const [tab, setTab] = useState('eleves')
  const [classes, setClasses] = useState([])
  const [annees, setAnnees] = useState([])
  const [filtres, setFiltres] = useState({ classe_id: '', annee: '' })
  const [effectif, setEffectif] = useState(null)
  const [refreshKey, setRefreshKey] = useState(0)

  useEffect(() => {
    fetchAllPages('/classes').then(setClasses)
    api.get('/eleves/annees').then(({ data }) => setAnnees(data))
  }, [refreshKey])

  // L'effectif affiché est celui des inscriptions actives, pas la valeur
  // saisie sur la fiche de classe : c'est lui qu'imprimera le bulletin.
  useEffect(() => {
    if (!filtres.classe_id) {
      setEffectif(null)
      return
    }
    const params = filtres.annee ? `?annee=${encodeURIComponent(filtres.annee)}` : ''
    api
      .get(`/classes/${filtres.classe_id}/eleves${params}`)
      .then(({ data }) => setEffectif(data.effectif))
  }, [filtres, refreshKey])

  const resource = useMemo(() => {
    const params = Object.entries(filtres).filter(([, v]) => v !== '')
    return params.length ? `/eleves?${new URLSearchParams(params)}` : '/eleves'
  }, [filtres])

  function definir(champ, valeur) {
    setFiltres((actuels) => ({ ...actuels, [champ]: valeur }))
  }

  const onglets = [
    ['eleves', 'Élèves'],
    ['inscriptions', 'Inscriptions'],
  ]

  return (
    <div>
      <h1 className="page-title mb-4">Élèves</h1>

      <div className="mb-4 flex flex-wrap gap-2">
        {onglets.map(([cle, label]) => (
          <button key={cle} onClick={() => setTab(cle)} className={tab === cle ? 'btn-primary' : 'btn-secondary'}>
            {label}
          </button>
        ))}
      </div>

      {tab === 'eleves' && (
        <>
          <SpreadsheetActions
            entity="eleves"
            label="élèves"
            hint="Format de la liste de classe : un onglet par classe, titré « LISTE DES ÉLÈVES — classe — année », un élève par ligne à partir de la ligne 4. Statut N (nouveau) ou R (redoublant). Un onglet titré « SANS CLASSE » enregistre l’identité sans inscription."
            onImported={() => setRefreshKey((k) => k + 1)}
          />

          <div className="mb-4 flex flex-wrap items-end gap-3">
            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Classe</span>
              <select
                value={filtres.classe_id}
                onChange={(e) => definir('classe_id', e.target.value)}
                className="field w-56 py-2"
              >
                <option value="">Toutes</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.code} · {c.nom}
                  </option>
                ))}
              </select>
            </label>

            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Année scolaire</span>
              <select
                value={filtres.annee}
                onChange={(e) => definir('annee', e.target.value)}
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

            {effectif !== null && (
              <span className="pb-2 text-sm text-ink-500">
                Effectif inscrit : <strong className="text-ink-900">{effectif}</strong>
              </span>
            )}
          </div>

          <ResourceTable
            key={`${resource}|${refreshKey}`}
            title="Fiches élèves"
            createLabel="Nouvel élève"
            resource={resource}
            fields={[
              { key: 'matricule', label: 'Matricule interne', required: true },
              { key: 'matricule_national', label: 'Matricule national' },
              { key: 'nom', label: 'Nom', required: true },
              { key: 'prenoms', label: 'Prénoms' },
              {
                key: 'sexe',
                label: 'Sexe',
                type: 'select',
                options: [
                  { value: 'F', label: 'Féminin' },
                  { value: 'M', label: 'Masculin' },
                ],
              },
              { key: 'date_naissance', label: 'Date de naissance', toForm: (v) => (v ? String(v).slice(0, 10) : '') },
              { key: 'lieu_naissance', label: 'Lieu de naissance' },
              { key: 'telephone', label: 'Téléphone' },
            ]}
            columns={[
              { key: 'matricule', label: 'Matricule' },
              { key: 'nom_complet', label: 'Nom' },
              { key: 'sexe', label: 'Sexe' },
              {
                key: 'date_naissance',
                label: 'Né(e) le',
                render: (row) => (row.date_naissance ? String(row.date_naissance).slice(0, 10) : '—'),
              },
              { key: 'lieu_naissance', label: 'À' },
              { key: 'telephone', label: 'Téléphone', render: (row) => row.telephone || '—' },
              {
                key: 'inscriptions',
                label: 'Classe',
                render: (row) => row.inscriptions?.[0]?.classe?.code ?? '—',
              },
              {
                key: 'statut',
                label: 'Statut',
                render: (row) =>
                  row.inscriptions?.[0]?.statut === 'redoublant' ? 'Redoublant' : row.inscriptions?.[0] ? 'Régulier' : '—',
              },
            ]}
          />
        </>
      )}

      {tab === 'inscriptions' && (
        <ResourceTable
          key={refreshKey}
          title="Inscriptions"
          subtitle="Un élève n’a qu’une inscription par année : changer de classe en cours d’année modifie celle-ci, il n’y en a pas deux."
          createLabel="Nouvelle inscription"
          resource="/inscriptions"
          fields={[
            {
              key: 'eleve_id',
              label: 'Élève',
              type: 'select',
              optionsUrl: '/eleves',
              optionLabel: 'nom_complet',
              required: true,
            },
            {
              key: 'classe_id',
              label: 'Classe',
              type: 'select',
              optionsUrl: '/classes',
              optionLabel: 'nom',
              required: true,
            },
            { key: 'annee_scolaire', label: 'Année scolaire (vide = en cours)' },
            {
              key: 'statut',
              label: 'Statut',
              type: 'select',
              options: [
                { value: 'regulier', label: 'Régulier' },
                { value: 'redoublant', label: 'Redoublant' },
              ],
            },
          ]}
          columns={[
            { key: 'eleve', label: 'Élève', render: (row) => row.eleve?.nom_complet ?? '—' },
            { key: 'matricule', label: 'Matricule', render: (row) => row.eleve?.matricule ?? '—' },
            { key: 'classe', label: 'Classe', render: (row) => row.classe?.code ?? '—' },
            { key: 'annee_scolaire', label: 'Année' },
            { key: 'statut', label: 'Statut', render: (row) => (row.statut === 'redoublant' ? 'Redoublant' : 'Régulier') },
            { key: 'actif', label: 'Actif', render: (row) => (row.actif ? 'Oui' : 'Non') },
          ]}
        />
      )}
    </div>
  )
}
