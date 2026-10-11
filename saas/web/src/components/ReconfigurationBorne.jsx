import { useState } from "react";
import { useTenant } from "../context/TenantContext";
import BlocCommandes from "./BlocCommandes";

/**
 * Modifier les données enregistrées dans la mémoire d'une borne déjà en
 * service : réseau WiFi, libellé, ou remise à l'état d'usine.
 *
 * Ce que cet écran ne fait pas, et pourquoi : il n'écrit rien dans la borne.
 * Sa mémoire (NVS) n'est accessible que par le port série — le firmware
 * n'interroge l'API que pour le manifest des mises à jour, il n'a aucun canal
 * de configuration à distance. L'écran compose donc les commandes exactes, ce
 * qui supprime la recopie à la main et l'erreur de code d'établissement.
 *
 * Le code de l'établissement n'est volontairement pas modifiable ici : voir
 * l'avertissement rendu en bas de ce composant.
 */
export default function ReconfigurationBorne({ device }) {
  const { code } = useTenant();
  const [ssid, setSsid] = useState("");
  const [motDePasse, setMotDePasse] = useState("");
  const [label, setLabel] = useState(device?.label ?? "");
  const [effacer, setEffacer] = useState(false);

  const labelInitial = device?.label ?? "";

  /*
    Seules les lignes correspondant à un champ réellement rempli sont émises :
    un `set` écrase, donc envoyer `set ssid` vide ou inchangé ferait réécrire
    pour rien — et un mot de passe WiFi laissé vide par distraction couperait
    la borne du réseau.
  */
  const lignes = effacer
    ? ["clear", "reboot"]
    : [
        ssid.trim() ? `set ssid ${ssid.trim()}` : null,
        motDePasse ? `set wifi ${motDePasse}` : null,
        label.trim() && label.trim() !== labelInitial
          ? `set label ${label.trim()}`
          : null,
      ].filter(Boolean);

  const lignesCompletes =
    lignes.length > 0 && !effacer ? [...lignes, "show", "reboot"] : lignes;

  const wifiIncomplet =
    !effacer && Boolean(ssid.trim()) !== Boolean(motDePasse);

  return (
    <div className="space-y-4">
      <p className="text-sm text-ink-600">
        Branchez la borne en USB, ouvrez le moniteur série à 115200 bauds et
        collez le bloc ci-dessous. Les valeurs non renseignées restent
        inchangées.
      </p>

      <fieldset
        disabled={effacer}
        className="space-y-3 disabled:opacity-50"
      >
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block text-sm">
            <span className="field-label">Nouveau réseau WiFi</span>
            <input
              type="text"
              value={ssid}
              onChange={(e) => setSsid(e.target.value)}
              placeholder="laisser vide = inchangé"
              className="field w-full"
            />
          </label>
          <label className="block text-sm">
            <span className="field-label">Mot de passe WiFi</span>
            <input
              type="text"
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
              placeholder="laisser vide = inchangé"
              className="field w-full"
            />
          </label>
        </div>

        {/*
          SSID sans mot de passe (ou l'inverse) : la borne garderait l'ancienne
          moitié et n'arriverait pas à se connecter. Mieux vaut le dire avant
          le déplacement sur site que de laisser découvrir une borne muette.
        */}
        {wifiIncomplet && (
          <p className="text-xs font-semibold text-gold-700">
            Renseignez le réseau <em>et</em> le mot de passe : changer l’un sans
            l’autre laisse la borne incapable de se connecter.
          </p>
        )}

        <label className="block text-sm">
          <span className="field-label">Emplacement</span>
          <input
            type="text"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="ex. Entrée principale"
            className="field w-full"
          />
          <span className="mt-1 block text-xs text-ink-400">
            Nom annoncé en Bluetooth, purement cosmétique.
          </span>
        </label>
      </fieldset>

      <label className="flex cursor-pointer items-start gap-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm">
        <input
          type="checkbox"
          checked={effacer}
          onChange={(e) => setEffacer(e.target.checked)}
          className="mt-0.5 h-4 w-4 accent-red-600"
        />
        <span>
          <span className="font-bold text-red-700">
            Effacer toute l’identité
          </span>
          <span className="mt-0.5 block text-xs text-ink-600">
            La borne repasse à l’état d’usine : invisible en Bluetooth, elle
            n’accepte plus aucun pointage jusqu’à une nouvelle mise en service.
            À faire avant de la déplacer vers un autre établissement.
          </span>
        </span>
      </label>

      <BlocCommandes
        lignes={lignesCompletes}
        titre="À coller dans le moniteur série"
      >
        {lignesCompletes.length === 0 && (
          <p className="mt-1.5 text-xs text-ink-400">
            Renseignez au moins une valeur à modifier.
          </p>
        )}
      </BlocCommandes>

      {/*
        Le code d'établissement est le seul champ absent de cet écran, et c'est
        délibéré : s'il change, les pointages encore en file sont détruits, et
        il faut de toute façon un nouveau token émis par l'autre établissement.
        Un champ texte ici donnerait l'illusion d'une bascule en un clic.
      */}
      <div className="rounded-2xl border border-gold-300 bg-gold-100 p-3 text-xs text-ink-700">
        <div className="flex items-center gap-1.5 font-bold text-gold-700">
          <span
            aria-hidden="true"
            className="material-symbols-rounded text-[16px]"
          >
            warning
          </span>
          Changer d’établissement ne se fait pas ici
        </div>
        <p className="mt-1">
          Cette borne est rattachée à <strong>{code}</strong>. Pour la déplacer
          vers un autre établissement :
        </p>
        <ol className="mt-1.5 ml-4 list-decimal space-y-0.5">
          <li>
            laissez-la connectée jusqu’à ce que sa file soit vide, et
            vérifiez-le au redémarrage sur la ligne{" "}
            <code className="font-mono">
              [queue] 0 paquet(s) en attente
            </code>{" "}
            — les pointages encore en attente seraient envoyés à la nouvelle
            base, refusés comme « Token enseignant invalide », puis
            <strong> purgés définitivement</strong> ;
          </li>
          <li>effacez l’identité avec la case ci-dessus ;</li>
          <li>
            déclarez la borne dans le backoffice de l’autre établissement : le
            token de <strong>{code}</strong> n’y vaut rien.
          </li>
        </ol>
      </div>
    </div>
  );
}
