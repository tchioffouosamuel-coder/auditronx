import { useState } from "react";
import { useTenant } from "../context/TenantContext";

/**
 * Fiche de mise en service d'une borne : le token fraîchement émis, et le bloc
 * de commandes à coller dans le moniteur série de la borne.
 *
 * La mise en service passait par deux `curl` et une recopie à la main du code
 * d'établissement et d'un token de cinquante caractères. Deux pièges s'y
 * cachaient : un code d'établissement erroné fait écrire les pointages dans la
 * base d'un autre lycée, et un token recopié de travers laisse une borne
 * muette que seul son journal série explique. Ici le backoffice connaît le
 * code et vient d'émettre le token : il compose le bloc lui-même.
 *
 * Le SSID et le mot de passe WiFi ne servent qu'à composer ce bloc côté
 * navigateur. Ils ne sont jamais envoyés à l'API : le réseau du site ne la
 * concerne pas, et un mot de passe WiFi stocké sans raison est un mot de passe
 * de plus à protéger.
 */
export default function MiseEnServiceBorne({ device, token }) {
  const { code } = useTenant();
  const [ssid, setSsid] = useState("");
  const [motDePasse, setMotDePasse] = useState("");
  const [copie, setCopie] = useState(false);

  const label = device?.label || device?.device_uuid || "";

  const lignes = [
    `set tenant ${code}`,
    `set token ${token}`,
    ssid ? `set ssid ${ssid}` : "set ssid VOTRE_RESEAU_WIFI",
    motDePasse ? `set wifi ${motDePasse}` : "set wifi VOTRE_MOT_DE_PASSE_WIFI",
    label ? `set label ${label}` : null,
    "show",
    "reboot",
  ].filter(Boolean);

  const bloc = lignes.join("\n");
  const complet = Boolean(ssid && motDePasse);

  async function copier() {
    try {
      await navigator.clipboard.writeText(bloc);
      setCopie(true);
      setTimeout(() => setCopie(false), 2500);
    } catch {
      // Presse-papiers refusé (contexte non sécurisé, permission) : le bloc
      // reste sélectionnable à la main, inutile d'alerter.
      setCopie(false);
    }
  }

  return (
    <div className="space-y-4">
      {/*
        Le token ne sera plus jamais affiché : l'API n'en conserve qu'une
        empreinte. Le dire ici évite de fermer la fenêtre trop vite et de
        devoir en émettre un autre.
      */}
      <div className="rounded-2xl border border-gold-300 bg-gold-100 p-3">
        <div className="flex items-center gap-1.5 text-sm font-bold text-gold-700">
          <span aria-hidden="true" className="material-symbols-rounded text-[18px]">
            key
          </span>
          Token affiché une seule fois
        </div>
        <p className="mt-1 text-xs text-ink-600">
          L’API n’en garde qu’une empreinte. Si vous le perdez, il faudra en
          générer un nouveau, ce qui invalide celui-ci.
        </p>
        <code className="mt-2 block overflow-x-auto rounded-xl bg-white px-3 py-2 font-mono text-xs break-all text-ink-800">
          {token}
        </code>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-sm">
          <span className="field-label">Réseau WiFi du site</span>
          <input
            type="text"
            value={ssid}
            onChange={(e) => setSsid(e.target.value)}
            placeholder="ex. Auditron"
            className="field w-full"
          />
        </label>
        <label className="block text-sm">
          <span className="field-label">Mot de passe WiFi</span>
          <input
            type="text"
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            placeholder="laissé en clair : à recopier dans la borne"
            className="field w-full"
          />
        </label>
      </div>
      <p className="-mt-2 text-xs text-ink-400">
        Ces deux champs ne quittent pas votre navigateur : ils servent
        uniquement à compléter le bloc ci-dessous.
      </p>

      <div>
        <div className="mb-1.5 flex items-center justify-between gap-2">
          <span className="section-title">
            À coller dans le moniteur série de la borne
          </span>
          <button type="button" onClick={copier} className="btn-secondary px-2.5 py-1 text-xs">
            <span aria-hidden="true" className="material-symbols-rounded text-[16px]">
              {copie ? "check" : "content_copy"}
            </span>
            {copie ? "Copié" : "Copier"}
          </button>
        </div>
        <pre className="overflow-x-auto rounded-xl bg-brand-950 px-3 py-2.5 font-mono text-xs leading-relaxed text-brand-100">
          {bloc}
        </pre>
        {!complet && (
          <p className="mt-1.5 text-xs font-medium text-gold-700">
            Renseignez le WiFi ci-dessus, ou remplacez les deux valeurs en
            majuscules avant de coller.
          </p>
        )}
      </div>

      <ol className="space-y-1.5 rounded-2xl bg-brand-50 p-3 text-xs text-ink-600">
        <li>
          <strong className="text-ink-800">1.</strong> Branchez la borne en USB
          et ouvrez le moniteur série à 115200 bauds.
        </li>
        <li>
          <strong className="text-ink-800">2.</strong> Collez le bloc, ligne par
          ligne ou d’un coup.
        </li>
        <li>
          <strong className="text-ink-800">3.</strong> Au redémarrage, vérifiez
          la ligne <code className="font-mono">[identite] etablissement={code}</code>{" "}
          — si un autre code apparaît, n’allez pas plus loin.
        </li>
        <li>
          <strong className="text-ink-800">4.</strong> Relevez{" "}
          <code className="font-mono">[ble] adresse=…</code> et déclarez-la dans
          l’onglet « Bornes (BLE) » : un pointage venu d’une adresse non
          déclarée est <strong>rejeté définitivement</strong>, pas réessayé.
        </li>
      </ol>
    </div>
  );
}
