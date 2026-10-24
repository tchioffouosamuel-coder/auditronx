import { useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import SpreadsheetActions from '../components/SpreadsheetActions'

/**
 * Créneaux canoniques de l'établissement — alignés sur
 * `EmploiDuTemps::HEURES_DEBUT` / `HEURES_FIN` côté API et sur
 * `AdminEmploisScreen` côté mobile. L'API valide `date_format:H:i` : une
 * saisie libre (« 8h », « 08:10:00 ») partait en 422 et bloquait la création
 * des emplois du temps depuis le web.
 */
const HEURES_DEBUT = [
  '07:30',
  '08:10',
  '08:50',
  '09:30',
  '10:10',
  '10:20',
  '11:00',
  '11:40',
  '12:00',
  '12:40',
  '13:20',
  '13:30',
  '14:10',
  '14:50',
  '15:30',
]

const HEURES_FIN = [
  '08:10',
  '08:50',
  '09:30',
  '10:10',
  '10:20',
  '11:00',
  '11:40',
  '12:00',
  '12:40',
  '13:20',
  '13:30',
  '14:10',
  '14:50',
  '15:30',
  '16:10',
]

/** « 08:10 » / « 08:10:00 » -> { value: '08:10', label: '08h10' }. */
const toHeureOption = (heure) => ({ value: heure, label: heure.replace(':', 'h') })

/** Une colonne SQL `time` revient en « 08:10:00 » : on retombe sur « HH:MM ». */
const toHeureMinute = (valeur) => (valeur ? String(valeur).slice(0, 5) : '')

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
          { key: 'heure_debut', label: 'Début', render: (r) => toHeureMinute(r.heure_debut) },
          { key: 'heure_fin', label: 'Fin', render: (r) => toHeureMinute(r.heure_fin) },
          { key: 'salle', label: 'Salle' },
        ]}
        fields={[
          { key: 'enseignant_id', label: 'Enseignant', type: 'select', optionsUrl: '/personnel?per_page=500', required: true },
          { key: 'classe_id', label: 'Classe', type: 'select', optionsUrl: '/classes', required: true },
          { key: 'discipline_id', label: 'Discipline', type: 'select', optionsUrl: '/disciplines', required: true },
          { key: 'jour', label: 'Jour', type: 'select', options: JOURS, required: true },
          {
            key: 'heure_debut',
            label: 'Heure de début',
            type: 'select',
            options: HEURES_DEBUT.map(toHeureOption),
            toForm: toHeureMinute,
            required: true,
          },
          {
            key: 'heure_fin',
            label: 'Heure de fin',
            type: 'select',
            options: HEURES_FIN.map(toHeureOption),
            toForm: toHeureMinute,
            required: true,
          },
          { key: 'salle', label: 'Salle' },
          { key: 'type_cours', label: 'Type de cours' },
        ]}
      />
    </div>
  )
}
