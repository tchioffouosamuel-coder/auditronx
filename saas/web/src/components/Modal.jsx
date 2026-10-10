import { useEffect, useRef } from "react";

export default function Modal({ title, onClose, children }) {
  const panneau = useRef(null);

  /*
    Échap pour fermer, et défilement de la page bloqué derrière la boîte :
    jusqu'ici seul le bouton « ✕ » fermait la fenêtre, et faire défiler
    au-dessus de l'arrière-plan déplaçait la page cachée — on perdait sa place
    dans la liste en revenant.
  */
  useEffect(() => {
    function onKeyDown(event) {
      if (event.key === "Escape") onClose();
    }

    const overflowInitial = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", onKeyDown);

    // Le focus part sur le panneau : sans ça il reste sur le bouton qui a
    // ouvert la boîte, derrière l'overlay.
    panneau.current?.focus();

    return () => {
      document.body.style.overflow = overflowInitial;
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [onClose]);

  return (
    <div
      className="animate-fade-in fixed inset-0 z-50 flex items-end justify-center bg-brand-950/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      /* Clic sur le fond seulement : `onMouseDown` sur la cible exacte évite
         qu'une sélection de texte relâchée hors du panneau ferme la boîte. */
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <div
        ref={panneau}
        role="dialog"
        aria-modal="true"
        aria-label={typeof title === "string" ? title : undefined}
        tabIndex={-1}
        className="flex max-h-[90vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-float outline-none sm:rounded-2xl"
      >
        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-ink-100 px-6 py-4">
          <h2 className="text-base font-bold text-ink-900">{title}</h2>
          <button
            onClick={onClose}
            aria-label="Fermer"
            className="-mr-2 shrink-0 rounded-xl p-1.5 text-ink-400 transition hover:bg-ink-50 hover:text-ink-700"
          >
            <span aria-hidden="true" className="material-symbols-rounded text-[20px]">
              close
            </span>
          </button>
        </div>
        <div className="overflow-y-auto px-6 py-4">{children}</div>
      </div>
    </div>
  );
}
