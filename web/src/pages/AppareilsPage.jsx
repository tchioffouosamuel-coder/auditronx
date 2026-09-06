import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import ResourceTable from '../components/ResourceTable'
import api from '../lib/api'
import { printQrCode } from '../lib/printQrCode'
import { confirmAction } from '../lib/swal'

function DevicesTable() {
  const [devices, setDevices] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    api
      .get('/devices')
      .then(({ data }) => setDevices(data.data ?? []))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function revoke(device) {
    if (!(await confirmAction(`Révoquer le device ${device.device_uuid} ?`, { confirmText: 'Révoquer' }))) return
    await api.post(`/devices/${device.id}/revoke`)
    load()
  }

  return (
    <DataTable
      loading={loading}
      emptyMessage="Aucun device."
      rows={devices}
      columns={[
        { key: 'teacher', label: 'Enseignant', render: (d) => d.teacher?.nom ?? '—', sortValue: (d) => d.teacher?.nom ?? '' },
        { key: 'device_uuid', label: 'UUID', render: (d) => <span className="font-mono text-xs">{d.device_uuid}</span> },
        { key: 'device_type', label: 'Type' },
        {
          key: 'activated_at',
          label: 'Activé le',
          render: (d) => (d.activated_at ? new Date(d.activated_at).toLocaleString('fr-FR') : '—'),
          sortValue: (d) => d.activated_at ?? '',
        },
        {
          key: 'statut',
          label: 'Statut',
          render: (d) => (d.revoked_at ? <span className="text-red-600">Révoqué</span> : <span className="text-green-600">Actif</span>),
          searchValue: (d) => (d.revoked_at ? 'Révoqué' : 'Actif'),
          sortValue: (d) => (d.revoked_at ? 1 : 0),
        },
      ]}
      renderActions={(d) =>
        !d.revoked_at && (
          <button onClick={() => revoke(d)} className="text-red-500 hover:text-red-700">
            Révoquer
          </button>
        )
      }
    />
  )
}

/**
 * Demandes d'activation (§4.1 revu, §otp-approval) : un enseignant non-admin
 * s'est identifié (tel + mot de passe). L'OTP est déjà généré côté serveur ;
 * une notification de validation (Valider/Refuser) a été poussée aux admins
 * connectés — ce tableau est le relais/secours pour agir depuis le backoffice
 * si la notification n'a pas été reçue.
 */
function ActivationRequestsTable() {
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  function load() {
    setLoading(true)
    api
      .get('/devices/activation-requests', { params: { statut: 'en_attente' } })
      .then(({ data }) => setRequests(data.data ?? []))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function approve(request) {
    setError(null)
    try {
      await api.post(`/devices/activation-requests/${request.id}/approve`)
      load()
    } catch (e) {
      setError(e.response?.data?.message ?? "Échec de l'envoi du code.")
    }
  }

  async function reject(request) {
    if (!(await confirmAction(`Refuser la demande de ${request.enseignant?.nom} ?`, { confirmText: 'Refuser' }))) return
    await api.post(`/devices/activation-requests/${request.id}/reject`)
    load()
  }

  return (
    <>
      {error && <p className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
      <DataTable
        loading={loading}
        emptyMessage="Aucune demande en attente."
        rows={requests}
        columns={[
          { key: 'enseignant', label: 'Enseignant', render: (r) => r.enseignant?.nom, sortValue: (r) => r.enseignant?.nom },
          { key: 'tel', label: 'Téléphone', render: (r) => r.enseignant?.tel, sortValue: (r) => r.enseignant?.tel },
          {
            key: 'code',
            label: 'Code',
            sortable: false,
            render: (r) => (r.code ? <span className="font-mono font-semibold tracking-widest">{r.code}</span> : '—'),
          },
          {
            key: 'requested_at',
            label: 'Demandée le',
            render: (r) => new Date(r.requested_at).toLocaleString('fr-FR'),
            sortValue: (r) => r.requested_at,
          },
        ]}
        renderActions={(r) => (
          <div className="flex gap-2">
            <button onClick={() => approve(r)} className="rounded-md bg-brand-700 px-3 py-1 text-white hover:bg-brand-800">
              Valider
            </button>
            <button onClick={() => reject(r)} className="rounded-md border border-red-200 px-3 py-1 text-red-600 hover:bg-red-50">
              Refuser
            </button>
          </div>
        )}
      />
    </>
  )
}

export default function AppareilsPage() {
  const [tab, setTab] = useState('activation-requests')

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Appareils & points d’accès</h1>

      <div className="mb-4 flex gap-2">
        {[
          ['activation-requests', 'Demandes d’activation'],
          ['devices', 'Devices'],
          ['access-points', 'Bornes (BLE)'],
          ['qr-points', 'Points QR'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === key ? 'bg-brand-700 text-white' : 'bg-white text-ink-700 border border-ink-100'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'activation-requests' && <ActivationRequestsTable />}
      {tab === 'devices' && <DevicesTable />}
      {tab === 'access-points' && (
        <ResourceTable
          title=""
          resource="/access-points"
          fields={[
            { key: 'bssid', label: 'Adresse BLE de la borne', required: true, placeholder: 'ex. AA:BB:CC:DD:EE:FF' },
            { key: 'ssid', label: 'Nom BLE annoncé (facultatif)' },
            { key: 'label', label: 'Libellé' },
          ]}
          columns={[
            { key: 'bssid', label: 'Adresse BLE' },
            { key: 'ssid', label: 'Nom BLE' },
            { key: 'label', label: 'Libellé' },
          ]}
        />
      )}
      {tab === 'qr-points' && (
        <ResourceTable
          title=""
          resource="/qr-points"
          fields={[{ key: 'label', label: 'Libellé' }]}
          columns={[
            { key: 'code', label: 'Code' },
            { key: 'label', label: 'Libellé' },
          ]}
          extraRowActions={(row) => (
            <button
              onClick={() => printQrCode({ value: row.code, label: row.label })}
              className="mr-3 text-brand-700 hover:text-brand-900"
            >
              QR / Imprimer
            </button>
          )}
        />
      )}
    </div>
  )
}
