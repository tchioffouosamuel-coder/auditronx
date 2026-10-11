import { useEffect, useState } from "react";
import DataTable from "../components/DataTable";
import MiseEnServiceBorne from "../components/MiseEnServiceBorne";
import Modal from "../components/Modal";
import RelayQueueImport from "../components/RelayQueueImport";
import ResourceTable from "../components/ResourceTable";
import api from "../lib/api";
import { formatDateTime } from "../lib/datetime";
import { printQrCode } from "../lib/printQrCode";
import { confirmAction, notify } from "../lib/swal";

function Icone({ nom, className = "" }) {
  return (
    <span aria-hidden="true" className={`material-symbols-rounded text-[18px] ${className}`}>
      {nom}
    </span>
  );
}

function DevicesTable() {
  const [devices, setDevices] = useState([]);
  const [loading, setLoading] = useState(true);
  // Formulaire de déclaration d'une borne, puis fiche de mise en service.
  const [creation, setCreation] = useState(null);
  const [miseEnService, setMiseEnService] = useState(null);

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

    // Un token nu ne suffit pas : il doit repartir dans la NVS de la borne
    // avec le bon code d'établissement. On rouvre donc la fiche complète.
    setMiseEnService({ device: data.device ?? device, token: data.token });
    load();
  }

  async function declarer(event) {
    event.preventDefault();

    const uuid = creation.device_uuid.trim();

    if (!uuid) return;

    setCreation((c) => ({ ...c, envoi: true }));

    try {
      const { data } = await api.post("/devices/provision-relay", {
        device_uuid: uuid,
        label: creation.label.trim() || null,
      });

      setCreation(null);
      setMiseEnService({ device: data.device, token: data.token });
      load();
    } catch (err) {
      // 422 le plus souvent : un identifiant déjà pris. Le message de l'API
      // est plus précis que tout ce qu'on pourrait deviner ici.
      const erreurs = err.response?.data?.errors;
      notify(
        erreurs ? Object.values(erreurs).flat().join(" ") : "Déclaration impossible.",
        { title: "Échec", icon: "error" },
      );
      setCreation((c) => ({ ...c, envoi: false }));
    }
  }

  return (
    <>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-ink-500">
          Bornes relais déclarées dans cet établissement.
        </p>
        <button
          type="button"
          onClick={() => setCreation({ device_uuid: "", label: "", envoi: false })}
          className="btn-primary"
        >
          <Icone nom="add" />
          Déclarer une borne
        </button>
      </div>

      <DataTable
      loading={loading}
      emptyMessage="Aucune borne déclarée."
      rows={devices}
      columns={[
        {
          key: "label",
          label: "Borne",
          render: (d) => d.label || <span className="text-ink-300">—</span>,
          sortValue: (d) => d.label ?? "",
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

      {creation && (
        <Modal title="Déclarer une borne" onClose={() => setCreation(null)}>
          <form onSubmit={declarer}>
            <label className="mb-3 block text-sm">
              <span className="field-label">
                Identifiant technique
                <span aria-hidden="true" className="ml-0.5 text-gold-600">
                  *
                </span>
              </span>
              <input
                type="text"
                required
                autoFocus
                value={creation.device_uuid}
                onChange={(e) =>
                  setCreation((c) => ({ ...c, device_uuid: e.target.value }))
                }
                placeholder="ex. borne-ltm-02"
                className="field w-full font-mono"
              />
              <span className="mt-1 block text-xs text-ink-400">
                Unique dans cet établissement, et définitif : il nomme le token.
              </span>
            </label>

            <label className="mb-3 block text-sm">
              <span className="field-label">Emplacement</span>
              <input
                type="text"
                value={creation.label}
                onChange={(e) => setCreation((c) => ({ ...c, label: e.target.value }))}
                placeholder="ex. Entrée principale"
                className="field w-full"
              />
              <span className="mt-1 block text-xs text-ink-400">
                Facultatif, mais une liste d’identifiants techniques ne dit pas
                où se trouve la borne qu’on s’apprête à révoquer.
              </span>
            </label>

            <div className="mt-5 flex gap-2 border-t border-ink-100 pt-4">
              <button
                type="button"
                onClick={() => setCreation(null)}
                className="btn-secondary flex-1"
              >
                Annuler
              </button>
              <button type="submit" disabled={creation.envoi} className="btn-primary flex-1">
                {creation.envoi && (
                  <span
                    aria-hidden="true"
                    className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                  />
                )}
                {creation.envoi ? "Déclaration…" : "Déclarer"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {miseEnService && (
        <Modal title="Mise en service de la borne" onClose={() => setMiseEnService(null)}>
          <MiseEnServiceBorne
            device={miseEnService.device}
            token={miseEnService.token}
          />
        </Modal>
      )}
    </>
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
