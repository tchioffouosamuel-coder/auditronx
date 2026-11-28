import { useEffect, useMemo, useState } from 'react'
import ReferentielApcImport from '../components/ReferentielApcImport'
import ResourceTable from '../components/ResourceTable'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'

/**
 * Référentiel APC : les modules d'une spécialité, par niveau et par bloc.
 *
 * Deux usages se croisent ici. L'import du classeur de l'organisation
 * pédagogique pose le gros du référentiel d'un coup ; la table sert ensuite à
 * reprendre ce que le classeur ne porte pas — **les crédits, surtout**, qui
 * n'y figurent pas et sans lesquels la moyenne générale d'un bulletin ne peut
 * pas se calculer — et à renommer les codes fabriqués à l'import.
 */
export default function ReferentielPage() {
  const [specialites, setSpecialites] = useState([])
  const [blocs, setBlocs] = useState([])
  const [filtres, setFiltres] = useState({ specialite_id: '', niveau: '', bloc: '' })
  const [refreshKey, setRefreshKey] = useState(0)

  useEffect(() => {
    fetchAllPages('/specialites').then(setSpecialites)
    api.get('/modules/blocs').then(({ data }) => setBlocs(data))
  }, [refreshKey])

  // Les niveaux proposés sont ceux pour lesquels un référentiel existe :
  // offrir « Terminale » à un établissement qui n'a saisi que la Seconde
  // n'aide personne.
  const [niveaux, setNiveaux] = useState([])
  useEffect(() => {
    fetchAllPages('/modules').then((modules) =>
      setNiveaux([...new Set(modules.map((m) => m.niveau))].sort()),
    )
  }, [refreshKey])

  const resource = useMemo(() => {
    const params = Object.entries(filtres).filter(([, v]) => v !== '')
    return params.length ? `/modules?${new URLSearchParams(params)}` : '/modules'
  }, [filtres])

  function definir(champ, valeur) {
    setFiltres((actuels) => ({ ...actuels, [champ]: valeur }))
  }

  const champs = [
    {
      key: 'specialite_id',
      label: 'Spécialité',
      type: 'select',
      optionsUrl: '/specialites',
      optionLabel: 'nom',
      required: true,
    },
    { key: 'niveau', label: 'Niveau', required: true },
    { key: 'code', label: 'Code', required: true },
    { key: 'intitule', label: 'Intitulé', required: true },
    {
      key: 'bloc',
      label: 'Bloc',
      type: 'select',
      required: true,
      options: blocs.map((b) => ({ value: b.valeur, label: b.libelle })),
    },
    { key: 'volume_horaire', label: 'Volume horaire (h)', type: 'number' },
    { key: 'credit', label: 'Crédit', type: 'number' },
    { key: 'ordre', label: 'Ordre', type: 'number' },
  ]

  const libelleBloc = (valeur) => blocs.find((b) => b.valeur === valeur)?.libelle ?? valeur

  return (
    <div>
      <h1 className="page-title mb-4">Référentiel APC</h1>

      <ReferentielApcImport onImported={() => setRefreshKey((k) => k + 1)} />

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Spécialité</span>
          <select
            value={filtres.specialite_id}
            onChange={(e) => definir('specialite_id', e.target.value)}
            className="field w-56 py-2"
          >
            <option value="">Toutes</option>
            {specialites.map((s) => (
              <option key={s.id} value={s.id}>
                {s.code} · {s.nom}
              </option>
            ))}
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Niveau</span>
          <select
            value={filtres.niveau}
            onChange={(e) => definir('niveau', e.target.value)}
            className="field w-40 py-2"
          >
            <option value="">Tous</option>
            {niveaux.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Bloc</span>
          <select value={filtres.bloc} onChange={(e) => definir('bloc', e.target.value)} className="field w-72 py-2">
            <option value="">Tous</option>
            {blocs.map((b) => (
              <option key={b.valeur} value={b.valeur}>
                {b.libelle}
              </option>
            ))}
          </select>
        </label>
      </div>

      <ResourceTable
        key={`${resource}|${refreshKey}`}
        title="Modules"
        subtitle="Les crédits ne figurent pas dans le classeur de l’organisation pédagogique : ils se saisissent ici, et conditionnent le calcul de la moyenne générale."
        createLabel="Nouveau module"
        resource={resource}
        fields={champs}
        columns={[
          { key: 'code', label: 'Code' },
          { key: 'intitule', label: 'Intitulé' },
          { key: 'bloc', label: 'Bloc', render: (row) => libelleBloc(row.bloc) },
          { key: 'specialite', label: 'Spécialité', render: (row) => row.specialite?.code ?? '—' },
          { key: 'niveau', label: 'Niveau' },
          { key: 'volume_horaire', label: 'VH', render: (row) => (row.volume_horaire ?? '—') },
          {
            key: 'credit',
            label: 'Crédit',
            render: (row) =>
              row.credit ?? <span className="text-amber-700">à saisir</span>,
          },
        ]}
      />
    </div>
  )
}
