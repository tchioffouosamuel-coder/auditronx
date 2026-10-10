import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

export default function ClassesPage() {
  const [refreshKey, setRefreshKey] = useState(0)

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
          { key: 'specialite', label: 'Spécialité' },
          { key: 'effectif', label: 'Effectif', type: 'number' },
        ]}
      />
    </div>
  )
}
