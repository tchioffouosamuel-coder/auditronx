import { useState } from "react";
import { normaliserCode } from "../lib/tenant";

/**
 * Choix de l'établissement sur l'écran de connexion du portail unique.
 *
 * Un seul chemin : le code de l'établissement. La liste des abonnés et la
 * recherche par téléphone ont été retirées — publier l'annuaire des
 * établissements sur une page ouverte n'apportait rien à qui connaît son
 * code, et le code est de toute façon communiqué par l'administration.
 */
export default function SelecteurEtablissement({ onChoisir }) {
  const [codeSaisi, setCodeSaisi] = useState("");

  return (
    <div className="space-y-5">
      <div className="text-center">
        <h2 className="text-lg font-semibold text-brand-900">Votre établissement</h2>
        <p className="text-sm text-ink-500">
          Saisissez son code une fois : il sera mémorisé sur cet appareil.
        </p>
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          const code = normaliserCode(codeSaisi);
          if (code) onChoisir(code);
        }}
        className="space-y-3"
      >
        <label className="block text-sm">
          <span className="field-label">Code de l’établissement</span>
          <input
            type="text"
            autoFocus
            placeholder="ex. LTM"
            value={codeSaisi}
            onChange={(e) => setCodeSaisi(e.target.value)}
            className="field w-full py-3 text-base uppercase tracking-widest"
          />
        </label>

        <p className="text-sm text-ink-500">
          Vous ne connaissez pas le code ? Demandez-le à votre administration.
        </p>

        <button type="submit" disabled={!normaliserCode(codeSaisi)} className="btn-primary w-full py-3">
          Continuer
        </button>
      </form>
    </div>
  );
}
