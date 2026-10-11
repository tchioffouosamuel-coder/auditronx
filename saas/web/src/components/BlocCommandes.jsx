import { useState } from "react";

/**
 * Bloc de commandes à coller dans le moniteur série d'une borne, avec bouton
 * de copie.
 *
 * Partagé par la mise en service et la reconfiguration : c'est le même geste,
 * et c'est tout l'intérêt de l'écran — le backoffice connaît le code de
 * l'établissement et les valeurs à poser, il compose les lignes lui-même
 * plutôt que de laisser recopier un token de cinquante caractères.
 */
export default function BlocCommandes({ lignes, titre, children }) {
  const [copie, setCopie] = useState(false);
  const bloc = lignes.join("\n");

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
    <div>
      <div className="mb-1.5 flex items-center justify-between gap-2">
        <span className="section-title">{titre}</span>
        <button
          type="button"
          onClick={copier}
          disabled={lignes.length === 0}
          className="btn-secondary px-2.5 py-1 text-xs"
        >
          <span
            aria-hidden="true"
            className="material-symbols-rounded text-[16px]"
          >
            {copie ? "check" : "content_copy"}
          </span>
          {copie ? "Copié" : "Copier"}
        </button>
      </div>
      <pre className="overflow-x-auto rounded-xl bg-brand-950 px-3 py-2.5 font-mono text-xs leading-relaxed text-brand-100">
        {bloc || "— rien à envoyer —"}
      </pre>
      {children}
    </div>
  );
}
