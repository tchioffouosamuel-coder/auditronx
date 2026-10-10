import { useState } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import PasswordInput from "../components/PasswordInput";
import SelecteurEtablissement from "../components/SelecteurEtablissement";
import { useAuth } from "../context/AuthContext";
import { useTenant } from "../context/TenantContext";

export default function LoginPage() {
  const { user, login } = useAuth();
  const { code, branding, catalogue, listePublique, choisir, changerEtablissement } = useTenant();
  const navigate = useNavigate();
  const [parametres] = useSearchParams();
  const [identifier, setIdentifier] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  if (user) return <Navigate to={user.nom ? "/enseignant" : "/"} replace />;

  // Message poussé par l'intercepteur HTTP (abonnement suspendu, code devenu
  // invalide) : il explique pourquoi l'utilisateur a été ramené ici.
  const motif = parametres.get("motif");

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const authenticatedUser = await login(identifier, password);
      navigate(authenticatedUser.nom ? "/enseignant" : "/");
    } catch {
      setError("Identifiants invalides.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
        <div className="mb-6 flex flex-col items-center text-center">
          <img
            src={branding?.logo_url || "/logo.png"}
            alt={branding?.nom || "Auditron X"}
            className="mb-3 h-16 w-16 object-contain"
          />
          <h1 className="text-xl font-bold text-brand-900">{branding?.nom || "Auditron X"}</h1>
          <p className="text-sm text-ink-500">
            {code ? "Connexion à votre espace" : "Plateforme de gestion de présence"}
          </p>
        </div>

        {motif && (
          <div className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{motif}</div>
        )}

        {/* Étape 1 : choisir l'établissement. Étape 2 : s'identifier. */}
        {!code ? (
          <SelecteurEtablissement
            catalogue={catalogue}
            listePublique={listePublique}
            onChoisir={choisir}
          />
        ) : (
          <form onSubmit={handleSubmit}>
            {error && (
              <div className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                {error}
              </div>
            )}

            <label className="mb-3 block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Email ou téléphone</span>
              <input
                type="text"
                required
                autoComplete="username"
                value={identifier}
                onChange={(e) => setIdentifier(e.target.value)}
                className="w-full rounded-lg border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
              />
            </label>

            <label className="mb-6 block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Mot de passe</span>
              <PasswordInput
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-lg border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
              />
            </label>

            <button
              type="submit"
              disabled={submitting}
              className="w-full rounded-lg bg-brand-700 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-50"
            >
              {submitting ? "Connexion…" : "Se connecter"}
            </button>

            <button
              type="button"
              onClick={changerEtablissement}
              className="mt-3 w-full text-center text-xs text-ink-500 underline transition hover:text-brand-700"
            >
              Changer d’établissement ({code})
            </button>
          </form>
        )}

        <p className="mt-6 border-t border-ink-100 pt-4 text-center text-xs text-ink-400">
          <Link to="/plateforme/connexion" className="underline hover:text-brand-700">
            Espace Auditron
          </Link>
        </p>
      </div>
    </div>
  );
}
