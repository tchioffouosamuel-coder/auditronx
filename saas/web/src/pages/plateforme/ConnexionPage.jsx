import { useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import PasswordInput from "../../components/PasswordInput";
import { usePlatformAuth } from "../../context/PlatformAuthContext";

export default function ConnexionPage() {
  const { compte, connexion } = usePlatformAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

  if (compte) return <Navigate to="/plateforme" replace />;

  async function soumettre(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);

    try {
      await connexion(email, password);
      navigate("/plateforme");
    } catch (e) {
      setErreur(
        e.response?.status === 422
          ? "Identifiants invalides ou compte désactivé."
          : "Connexion impossible pour le moment.",
      );
    } finally {
      setEnCours(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-ink-900 p-4">
      <form onSubmit={soumettre} className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
        <h1 className="mb-1 text-xl font-bold text-ink-900">Espace Auditron</h1>
        <p className="mb-6 text-sm text-ink-500">
          Administration des établissements abonnés.
        </p>

        {erreur && (
          <div className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{erreur}</div>
        )}

        <label className="mb-3 block text-sm">
          <span className="mb-1 block font-medium text-ink-700">Email</span>
          <input
            type="email"
            required
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="field w-full"
          />
        </label>

        <label className="mb-6 block text-sm">
          <span className="mb-1 block font-medium text-ink-700">Mot de passe</span>
          <PasswordInput
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="field w-full"
          />
        </label>

        <button
          type="submit"
          disabled={enCours}
          className="btn w-full bg-ink-900 py-2.5 text-white hover:bg-ink-800"
        >
          {enCours ? "Connexion…" : "Se connecter"}
        </button>

        <p className="mt-6 border-t border-ink-100 pt-4 text-center text-xs text-ink-400">
          <Link to="/login" className="underline hover:text-brand-700">
            Accès établissement
          </Link>
        </p>
      </form>
    </div>
  );
}
