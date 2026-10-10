import { useState } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import PasswordInput from "../components/PasswordInput";
import SelecteurEtablissement from "../components/SelecteurEtablissement";
import { useAuth } from "../context/AuthContext";
import { useTenant } from "../context/TenantContext";

export default function LoginPage() {
  const { user, login } = useAuth();
  const {
    code,
    branding,
    catalogue,
    listePublique,
    choisir,
    changerEtablissement,
  } = useTenant();
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
    <div className="flex min-h-screen items-center justify-center bg-transparent p-4">
      <div className="w-full max-w-md">
        <div className="rounded-[28px] border border-brand-200/80 bg-white/85 p-7 shadow-float backdrop-blur-sm">
          <div className="mb-6 flex flex-col items-center text-center">
            <div className="mb-3 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-brand-100 ring-8 ring-brand-50">
              <img
                src={branding?.logo_url || "/logo.png"}
                alt=""
                className="h-10 w-10 object-contain"
              />
            </div>
            <h1 className="text-3xl font-black tracking-tight text-brand-900 sm:text-4xl">
              {branding?.nom || "Auditron"}
            </h1>
            <p className="mt-2 text-sm font-medium text-ink-500">
              {code
                ? "Connexion à votre espace"
                : "Plateforme de gestion de présence"}
            </p>
          </div>

          {motif && (
            <div
              role="status"
              className="mb-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
              <span
                aria-hidden="true"
                className="material-symbols-rounded mt-px text-[18px]"
              >
                info
              </span>
              <span>{motif}</span>
            </div>
          )}

          {!code ? (
            <SelecteurEtablissement
              catalogue={catalogue}
              listePublique={listePublique}
              onChoisir={choisir}
            />
          ) : (
            <form onSubmit={handleSubmit}>
              {/*
                `role="alert"` plutôt qu'un simple encart : l'échec de connexion
                doit être annoncé aux lecteurs d'écran, qui autrement ne voient
                rien changer après la soumission.
              */}
              {error && (
                <div
                  role="alert"
                  className="mb-4 flex items-center gap-2 rounded-2xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700 ring-1 ring-red-200"
                >
                  <span
                    aria-hidden="true"
                    className="material-symbols-rounded text-[18px]"
                  >
                    error
                  </span>
                  {error}
                </div>
              )}

              <label className="mb-3 block text-sm">
                <span className="field-label">Email ou téléphone</span>
                <input
                  type="text"
                  required
                  autoComplete="username"
                  autoFocus
                  placeholder="nom@etablissement.ci"
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  className="field w-full"
                />
              </label>

              <label className="mb-6 block text-sm">
                <span className="field-label">Mot de passe</span>
                <PasswordInput
                  required
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="field w-full"
                />
              </label>

              <button
                type="submit"
                disabled={submitting}
                className="btn-primary w-full rounded-full py-3"
              >
                {submitting && (
                  <span
                    aria-hidden="true"
                    className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                  />
                )}
                {submitting ? "Connexion…" : "Se connecter"}
              </button>

              <button
                type="button"
                onClick={changerEtablissement}
                className="mt-3 w-full text-center text-xs font-medium text-ink-500 underline decoration-brand-300 underline-offset-4 transition hover:text-brand-700"
              >
                Changer d’établissement ({code})
              </button>
            </form>
          )}

          <p className="mt-6 border-t border-brand-100 pt-4 text-center text-xs text-ink-400">
            <Link
              to="/plateforme/connexion"
              className="font-semibold text-brand-700 underline-offset-4 hover:underline"
            >
              Espace Auditron
            </Link>
          </p>
        </div>

        <p className="mt-4 text-center text-xs text-ink-400">
          Auditron · suivi de présence par QR code
        </p>
      </div>
    </div>
  );
}
