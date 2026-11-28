import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'

/**
 * Départements et spécialités d'un établissement technique.
 *
 * Les deux vivaient jusqu'ici en texte libre — `disciplines.departement` et
 * `classes.specialite` — saisis à la main et donc orthographiés de plusieurs
 * façons. Ce sont désormais des entités : c'est sur elles que s'accroche le
 * référentiel APC, et c'est par elles que les bulletins se regroupent.
 *
 * Les spécialités apparaissent le plus souvent par l'import du référentiel,
 * qui les crée avec leur code pour libellé. L'écran sert alors à leur donner
 * leur nom complet et leur département : un onglet « F2 » ne dit pas s'il
 * s'agit d'électronique ou d'électrotechnique, et l'application ne l'invente
 * pas.
 */
export default function StructurePage() {
  const [tab, setTab] = useState('specialites')

  const onglets = [
    ['specialites', 'Spécialités'],
    ['departements', 'Départements'],
  ]

  return (
    <div>
      <h1 className="page-title mb-4">Structure</h1>

      <div className="mb-4 flex flex-wrap gap-2">
        {onglets.map(([cle, label]) => (
          <button key={cle} onClick={() => setTab(cle)} className={tab === cle ? 'btn-primary' : 'btn-secondary'}>
            {label}
          </button>
        ))}
      </div>

      {tab === 'specialites' && (
        <ResourceTable
          title="Spécialités"
          resource="/specialites"
          fields={[
            { key: 'code', label: 'Code', required: true },
            { key: 'nom', label: 'Nom', required: true },
            {
              key: 'departement_id',
              label: 'Département',
              type: 'select',
              optionsUrl: '/departements',
              optionLabel: 'nom',
            },
            { key: 'cycle', label: 'Cycle' },
          ]}
          columns={[
            { key: 'code', label: 'Code' },
            { key: 'nom', label: 'Nom' },
            { key: 'departement', label: 'Département', render: (row) => row.departement?.nom ?? '—' },
            { key: 'cycle', label: 'Cycle' },
            { key: 'modules_count', label: 'Modules' },
          ]}
        />
      )}

      {tab === 'departements' && (
        <ResourceTable
          title="Départements"
          resource="/departements"
          // Le nom suffit : le code se déduit côté API (« Génie Électrique » -> GE).
          fields={[{ key: 'nom', label: 'Nom', required: true }]}
          columns={[
            { key: 'code', label: 'Code' },
            { key: 'nom', label: 'Nom' },
            { key: 'specialites_count', label: 'Spécialités' },
          ]}
        />
      )}
    </div>
  )
}
