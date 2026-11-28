import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

export default function ClassesPage() {
  const [refreshKey, setRefreshKey] = useState(0)
  const navigate = useNavigate()

  return (
    <div>
      <SpreadsheetActions entity="classes" label="classes" onImported={() => setRefreshKey((k) => k + 1)} />
      <ResourceTable
        key={refreshKey}
        title="Classes"
        resource="/classes"
        fields={[
          { key: 'nom', label: 'Nom', required: true },
          { key: 'code', label: 'Code', required: true },
          { key: 'niveau', label: 'Niveau' },
          {
            key: 'specialite_id',
            label: 'Spécialité (référentiel APC)',
            type: 'select',
            optionsUrl: '/specialites',
            optionLabel: 'nom',
          },
          { key: 'effectif', label: 'Effectif', type: 'number' },
        ]}
        columns={[
          { key: 'nom', label: 'Nom' },
          { key: 'code', label: 'Code' },
          { key: 'niveau', label: 'Niveau' },
          {
            key: 'specialite',
            label: 'Spécialité',
            // La colonne texte est tenue en miroir du rattachement : elle
            // reste renseignée sur les classes saisies avant le référentiel.
            render: (row) => row.specialite_apc?.code ?? row.specialite ?? '—',
          },
          { key: 'effectif', label: 'Effectif' },
          {
            key: 'maquette_count',
            label: 'Maquette',
            render: (row) =>
              row.maquette_count > 0 ? `${row.maquette_count} module(s)` : '—',
          },
        ]}
        extraRowActions={(row) => (
          <button type="button" onClick={() => navigate(`/classes/${row.id}/maquette`)} className="btn-ghost">
            Maquette
          </button>
        )}
      />
    </div>
  )
}
