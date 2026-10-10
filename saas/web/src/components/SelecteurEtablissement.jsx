import { useMemo, useState } from "react";
import apiCentral from "../lib/apiCentral";
import { normaliserCode } from "../lib/tenant";

/**
 * Choix de l'établissement sur l'écran de connexion du portail unique.
 *
 * Trois chemins vers le même résultat, par ordre de confort :
 *  - la liste des abonnés, quand l'éditeur la publie ;
 *  - la saisie directe du code, toujours disponible ;
 *  - la recherche par numéro de téléphone, pour qui a oublié son code.
 */
export default function SelecteurEtablissement({ catalogue, listePublique, onChoisir }) {
  const [recherche, setRecherche] = useState("");
  const [codeSaisi, setCodeSaisi] = useState("");
  const [tel, setTel] = useState("");
  const [resultatsTel, setResultatsTel] = useState(null);
  const [message, setMessage] = useState(null);
  const [enCours, setEnCours] = useState(false);

  const filtres = useMemo(() => {
    const terme = recherche.trim().toLowerCase();

    if (!terme) return catalogue;

    return catalogue.filter((e) =>
      [e.nom, e.nom_court, e.code, e.ville]
        .filter(Boolean)
        .some((champ) => champ.toLowerCase().includes(terme)),
    );
  }, [catalogue, recherche]);

  async function chercherParTelephone(e) {
    e.preventDefault();
    setMessage(null);
    setEnCours(true);

    try {
      const { data } = await apiCentral.post("/annuaire/resolve", { tel });
      const trouves = data?.data ?? [];

      setResultatsTel(trouves);

      if (trouves.length === 0) {
        setMessage(
          "Aucun établissement trouvé pour ce numéro. Demandez le code à votre administration.",
        );
      }
    } catch {
      setMessage("Recherche impossible pour le moment. Saisissez le code de votre établissement.");
    } finally {
      setEnCours(false);
    }
  }

  const propositions = resultatsTel ?? filtres;

  return (
    <div className="space-y-5">
      <div className="text-center">
        <h2 className="text-lg font-semibold text-brand-900">Votre établissement</h2>
        <p className="text-sm text-ink-500">
          Sélectionnez-le une fois : il sera mémorisé sur cet appareil.
        </p>
      </div>

      {message && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{message}</div>
      )}

      {listePublique && (
        <>
          <input
            type="search"
            placeholder="Rechercher un établissement ou une ville…"
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value);
              setResultatsTel(null);
            }}
            className="field w-full"
          />

          <ul className="max-h-60 space-y-1.5 overflow-y-auto">
            {propositions.map((etablissement) => (
              <li key={etablissement.code}>
                <button
                  type="button"
                  onClick={() => onChoisir(etablissement.code, etablissement)}
                  className="flex w-full items-center gap-3 rounded-xl border border-ink-100 bg-white px-3 py-2.5 text-left transition hover:border-brand-400 hover:bg-brand-50 hover:shadow-card"
                >
                  {etablissement.logo_url ? (
                    <img
                      src={etablissement.logo_url}
                      alt=""
                      className="h-9 w-9 shrink-0 rounded-lg object-contain"
                    />
                  ) : (
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-xs font-bold text-brand-800">
                      {etablissement.code.slice(0, 3)}
                    </span>
                  )}
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-medium text-ink-800">
                      {etablissement.nom}
                    </span>
                    <span className="block truncate text-xs text-ink-500">
                      {[etablissement.ville, etablissement.code].filter(Boolean).join(" · ")}
                    </span>
                  </span>
                </button>
              </li>
            ))}

            {propositions.length === 0 && (
              <li className="px-1 py-2 text-sm text-ink-500">Aucun établissement ne correspond.</li>
            )}
          </ul>
        </>
      )}

      <form
        onSubmit={(e) => {
          e.preventDefault();
          const code = normaliserCode(codeSaisi);
          if (code) onChoisir(code);
        }}
        className="space-y-2 border-t border-ink-100 pt-4"
      >
        <label className="block text-sm">
          <span className="field-label">Code de l’établissement</span>
          <input
            type="text"
            placeholder="ex. LTM"
            value={codeSaisi}
            onChange={(e) => setCodeSaisi(e.target.value)}
            className="field w-full uppercase tracking-widest"
          />
        </label>
        <button
          type="submit"
          disabled={!normaliserCode(codeSaisi)}
          className="btn-primary w-full"
        >
          Continuer
        </button>
      </form>

      <form onSubmit={chercherParTelephone} className="space-y-2 border-t border-ink-100 pt-4">
        <label className="block text-sm">
          <span className="field-label">
            Code oublié ? Retrouvez-le avec votre numéro
          </span>
          <input
            type="tel"
            placeholder="ex. 699 00 11 22"
            value={tel}
            onChange={(e) => setTel(e.target.value)}
            className="field w-full"
          />
        </label>
        <button
          type="submit"
          disabled={enCours || tel.trim().length < 6}
          className="btn-secondary w-full"
        >
          {enCours ? "Recherche…" : "Rechercher"}
        </button>
      </form>
    </div>
  );
}
