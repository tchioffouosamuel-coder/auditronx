import { useEffect, useState } from "react";
import DataTable from "../components/DataTable";
import RelayQueueImport from "../components/RelayQueueImport";
import ResourceTable from "../components/ResourceTable";
import api from "../lib/api";
import { formatDateTime } from "../lib/datetime";
import { printQrCode } from "../lib/printQrCode";
import swal, { confirmAction } from "../lib/swal";

function DevicesTable() {
  const [devices, setDevices] = useState([]);
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    api
      .get("/devices")
      .then(({ data }) =>
        setDevices(
          (data.data ?? []).filter(
            (device) => device.device_type === "relay_gateway",
          ),
        ),
      )
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function revoke(device) {
    if (
      !(await confirmAction(`Révoquer le device ${device.device_uuid} ?`, {
        confirmText: "Révoquer",
      }))
    )
      return;
    await api.post(`/devices/${device.id}/revoke`);
    load();
  }

  async function rotateToken(device) {
    if (
      !(await confirmAction(
        `Générer un nouveau token pour ${device.device_uuid} ? L'ancien token sera immédiatement invalidé.`,
        {
          title: "Renouveler le token",
          confirmText: "Générer",
        },
      ))
    )
      return;

    const { data } = await api.post(`/devices/${device.id}/rotate-token`);
    await swal.fire({
      title: "Nouveau token",
      html: '<p class="mb-3 text-left text-sm">Copiez ce token maintenant. Il ne sera plus affiché ensuite.</p>',
      input: "text",
      inputValue: data.token,
      inputReadOnly: true,
      showCancelButton: true,
      confirmButtonText: "Copier",
      cancelButtonText: "Fermer",
      preConfirm: async () => {
        await navigator.clipboard.writeText(data.token);
      },
    });
    load();
  }

  return (
    <DataTable
      loading={loading}
      emptyMessage="Aucun device."
      rows={devices}
      columns={[
        {
          key: "teacher",
          label: "Enseignant",
          render: (d) => d.teacher?.nom ?? "—",
          sortValue: (d) => d.teacher?.nom ?? "",
        },
        {
          key: "device_uuid",
          label: "UUID",
          render: (d) => (
            <span className="font-mono text-xs">{d.device_uuid}</span>
          ),
        },
        { key: "device_type", label: "Type" },
        {
          key: "activated_at",
          label: "Activé le",
          render: (d) =>
            d.activated_at
              ? formatDateTime(d.activated_at, {})
              : "—",
          sortValue: (d) => d.activated_at ?? "",
        },
        {
          key: "statut",
          label: "Statut",
          render: (d) =>
            d.revoked_at ? (
              <span className="text-red-600">Révoqué</span>
            ) : (
              <span className="text-green-600">Actif</span>
            ),
          searchValue: (d) => (d.revoked_at ? "Révoqué" : "Actif"),
          sortValue: (d) => (d.revoked_at ? 1 : 0),
        },
      ]}
      renderActions={(d) =>
        (d.device_type === "relay_gateway" || !d.revoked_at) && (
          <div className="flex gap-3">
            {d.device_type === "relay_gateway" && (
              <button
                onClick={() => rotateToken(d)}
                className="text-brand-700 hover:text-brand-900"
              >
                {d.revoked_at ? "Réactiver + nouveau token" : "Nouveau token"}
              </button>
            )}
            <button
              onClick={() => revoke(d)}
              className="text-red-500 hover:text-red-700"
              hidden={Boolean(d.revoked_at)}
            >
              Révoquer
            </button>
          </div>
        )
      }
    />
  );
}

export default function AppareilsPage() {
  const [tab, setTab] = useState("devices");

  return (
    <div>
      <h1 className="page-title mb-1">Appareils &amp; points d’accès</h1>
      <p className="page-subtitle mb-4">
        Lecteurs, bornes Bluetooth et points QR installés dans
        l’établissement.
      </p>

      {/*
        Vrai `tablist` ARIA : c’étaient quatre boutons indépendants, sans lien
        annoncé avec le panneau affiché ni indication de l’onglet courant pour
        un lecteur d’écran.
      */}
      <div
        role="tablist"
        aria-label="Catégories d’appareils"
        className="mb-4 flex flex-wrap gap-1.5 rounded-2xl border border-brand-200/70 bg-white/70 p-1.5 backdrop-blur-sm"
      >
        {[
          ["devices", "Devices", "smartphone"],
          ["access-points", "Bornes (BLE)", "bluetooth"],
          ["qr-points", "Points QR", "qr_code_2"],
          ["import-queue", "Import file borne", "upload_file"],
        ].map(([key, label, icone]) => (
          <button
            key={key}
            role="tab"
            type="button"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold transition ${
              tab === key
                ? "bg-gradient-to-r from-brand-700 to-brand-500 text-white shadow-sm"
                : "text-ink-600 hover:bg-brand-50 hover:text-brand-800"
            }`}
          >
            <span aria-hidden="true" className="material-symbols-rounded text-[18px]">
              {icone}
            </span>
            {label}
          </button>
        ))}
      </div>

      {tab === "devices" && <DevicesTable />}
      {tab === "import-queue" && <RelayQueueImport />}
      {tab === "access-points" && (
        <ResourceTable
          title=""
          resource="/access-points"
          fields={[
            {
              key: "bssid",
              label: "Adresse BLE de la borne",
              required: true,
              placeholder: "ex. AA:BB:CC:DD:EE:FF",
            },
            { key: "ssid", label: "Nom BLE annoncé (facultatif)" },
            { key: "label", label: "Libellé" },
          ]}
          columns={[
            { key: "bssid", label: "Adresse BLE" },
            { key: "ssid", label: "Nom BLE" },
            { key: "label", label: "Libellé" },
          ]}
        />
      )}
      {tab === "qr-points" && (
        <ResourceTable
          title=""
          resource="/qr-points"
          fields={[{ key: "label", label: "Libellé" }]}
          columns={[
            { key: "code", label: "Code" },
            { key: "label", label: "Libellé" },
          ]}
          extraRowActions={(row) => (
            <button
              onClick={() => printQrCode({ value: row.code, label: row.label })}
              aria-label="Imprimer le QR code"
              title="Imprimer le QR code"
              className="rounded-lg p-1.5 text-ink-400 transition hover:bg-brand-50 hover:text-brand-700"
            >
              <span aria-hidden="true" className="material-symbols-rounded text-[18px]">
                print
              </span>
            </button>
          )}
        />
      )}
    </div>
  );
}
