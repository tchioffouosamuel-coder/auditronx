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
        <div className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{message}</div>
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
            className="w-full rounded-lg border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
          />

          <ul className="max-h-56 space-y-1 overflow-y-auto">
            {propositions.map((etablissement) => (
              <li key={etablissement.code}>
                <button
                  type="button"
                  onClick={() => onChoisir(etablissement.code, etablissement)}
                  className="flex w-full items-center gap-3 rounded-lg border border-ink-100 px-3 py-2 text-left transition hover:border-brand-400 hover:bg-brand-50"
                >
                  {etablissement.logo_url ? (
                    <img
                      src={etablissement.logo_url}
                      alt=""
                      className="h-8 w-8 rounded object-contain"
                    />
                  ) : (
                    <span className="flex h-8 w-8 items-center justify-center rounded bg-brand-100 text-xs font-bold text-brand-800">
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
          <span className="mb-1 block font-medium text-ink-700">Code de l’établissement</span>
          <input
            type="text"
            placeholder="ex. LTM"
            value={codeSaisi}
            onChange={(e) => setCodeSaisi(e.target.value)}
            className="w-full rounded-lg border border-ink-100 px-3 py-2 uppercase focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
          />
        </label>
        <button
          type="submit"
          disabled={!normaliserCode(codeSaisi)}
          className="w-full rounded-lg bg-brand-700 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-50"
        >
          Continuer
        </button>
      </form>

      <form onSubmit={chercherParTelephone} className="space-y-2 border-t border-ink-100 pt-4">
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-ink-700">
            Code oublié ? Retrouvez-le avec votre numéro
          </span>
          <input
            type="tel"
            placeholder="ex. 699 00 11 22"
            value={tel}
            onChange={(e) => setTel(e.target.value)}
            className="w-full rounded-lg border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
          />
        </label>
        <button
          type="submit"
          disabled={enCours || tel.trim().length < 6}
          className="w-full rounded-lg border border-brand-200 py-2 text-sm font-semibold text-brand-800 transition hover:bg-brand-50 disabled:opacity-50"
        >
          {enCours ? "Recherche…" : "Rechercher"}
        </button>
      </form>
    </div>
  );
}
