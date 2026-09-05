import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

export default function PersonnelPage() {
  const [refreshKey, setRefreshKey] = useState(0)

  return (
    <div>
      <SpreadsheetActions entity="personnel" label="personnel" onImported={() => setRefreshKey((k) => k + 1)} />

      <ResourceTable
        key={refreshKey}
        title="Personnel"
        resource="/personnel"
        fields={[
          { key: 'nom', label: 'Nom', required: true },
          { key: 'matricule', label: 'Matricule', required: true },
          { key: 'email', label: 'Email', type: 'email' },
          { key: 'fonction', label: 'Fonction' },
          { key: 'section', label: 'Section' },
          { key: 'grade', label: 'Grade' },
          { key: 'tel', label: 'Téléphone' },
          { key: 'poste', label: 'Poste' },
          { key: 'password', label: 'Mot de passe app mobile (laisser vide pour ne pas changer)', type: 'password' },
          { key: 'est_admin', label: 'Accès direct à l’app sans OTP (admin)', type: 'checkbox' },
        ]}
        columns={[
          { key: 'nom', label: 'Nom' },
          { key: 'matricule', label: 'Matricule' },
          { key: 'email', label: 'Email' },
          { key: 'fonction', label: 'Fonction' },
          { key: 'section', label: 'Section' },
          { key: 'grade', label: 'Grade' },
          { key: 'tel', label: 'Téléphone' },
          { key: 'poste', label: 'Poste' },
        ]}
      />
    </div>
  )
}
