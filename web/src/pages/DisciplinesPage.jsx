import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

export default function DisciplinesPage() {
  const [refreshKey, setRefreshKey] = useState(0)

  return (
    <div>
      <SpreadsheetActions entity="disciplines" label="disciplines" onImported={() => setRefreshKey((k) => k + 1)} />
      <ResourceTable
        key={refreshKey}
        title="Disciplines"
        resource="/disciplines"
        fields={[
          { key: 'nom', label: 'Nom', required: true },
          { key: 'code', label: 'Code', required: true },
          { key: 'coefficient', label: 'Coefficient', type: 'number' },
          { key: 'departement', label: 'Département' },
        ]}
      />
    </div>
  )
}
