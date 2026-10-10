import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

const JOURS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
]

export default function EmploisPage() {
  const [refreshKey, setRefreshKey] = useState(0)

  return (
    <div>
      <SpreadsheetActions entity="emplois" label="emplois du temps" onImported={() => setRefreshKey((k) => k + 1)} />
      <ResourceTable
        key={refreshKey}
        title="Emplois du temps"
        resource="/emplois"
        idKey="id"
        columns={[
          { key: 'enseignant', label: 'Enseignant', render: (r) => r.enseignant?.nom },
          { key: 'classe', label: 'Classe', render: (r) => r.classe?.nom },
          { key: 'discipline', label: 'Discipline', render: (r) => r.discipline?.nom },
          { key: 'jour', label: 'Jour', render: (r) => JOURS.find((j) => j.value === r.jour)?.label },
          { key: 'heure_debut', label: 'Début' },
          { key: 'heure_fin', label: 'Fin' },
          { key: 'salle', label: 'Salle' },
        ]}
        fields={[
          { key: 'enseignant_id', label: 'Enseignant', type: 'select', optionsUrl: '/personnel?per_page=500', required: true },
          { key: 'classe_id', label: 'Classe', type: 'select', optionsUrl: '/classes', required: true },
          { key: 'discipline_id', label: 'Discipline', type: 'select', optionsUrl: '/disciplines', required: true },
          { key: 'jour', label: 'Jour', type: 'select', options: JOURS, required: true },
          { key: 'heure_debut', label: 'Heure de début (HH:MM)', required: true },
          { key: 'heure_fin', label: 'Heure de fin (HH:MM)', required: true },
          { key: 'salle', label: 'Salle' },
          { key: 'type_cours', label: 'Type de cours' },
        ]}
      />
    </div>
  )
}
