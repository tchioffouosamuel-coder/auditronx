import { useEffect, useState } from "react";
import LoadingState from "../../components/LoadingState";
import Modal from "../../components/Modal";
import { usePlatformAuth } from "../../context/PlatformAuthContext";
import apiCentral from "../../lib/apiCentral";

const ROLES = [
  { value: "super_admin", label: "Super-administrateur" },
  { value: "support", label: "Support" },
  { value: "commercial", label: "Commercial" },
];

export default function UtilisateursPage() {
  const { compte } = usePlatformAuth();
  const [utilisateurs, setUtilisateurs] = useState(null);
  const [erreur, setErreur] = useState("");
  const [edition, setEdition] = useState(null);
  const [identifiants, setIdentifiants] = useState(null);
  const [enCours, setEnCours] = useState(false);

  async function chargerUtilisateurs() {
    const { data } = await apiCentral.get("/utilisateurs");
    return data.data;
  }

  async function recharger() {
    setUtilisateurs(await chargerUtilisateurs());
  }

  useEffect(() => {
    chargerUtilisateurs()
      .then(setUtilisateurs)
      .catch(() => setErreur("Impossible de charger les comptes Auditron."));
  }, []);

  async function enregistrer(event) {
    event.preventDefault();
    setErreur("");
    setEnCours(true);
    try {
      if (edition.id) {
        const { password, ...compteModifie } = edition;
        const payload = password ? { ...compteModifie, password } : compteModifie;
        await apiCentral.put(`/utilisateurs/${edition.id}`, payload);
        setEdition(null);
        await recharger();
      } else {
        const { data } = await apiCentral.post("/utilisateurs", edition);
        setEdition(null);
        setIdentifiants({ utilisateur: data.data, password: data.password_initial });
        await recharger();
      }
    } catch (error) {
      setErreur(error.response?.data?.message ?? "Enregistrement impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function changerActif(utilisateur) {
    setErreur("");
    try {
      await apiCentral.put(`/utilisateurs/${utilisateur.id}`, { actif: !utilisateur.actif });
      await recharger();
    } catch (error) {
      setErreur(error.response?.data?.message ?? "Modification impossible.");
    }
  }

  if (!utilisateurs) return <LoadingState />;

  const estMoi = (utilisateur) => String(utilisateur.id) === String(compte?.id);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="page-title">Équipe Auditron</h1>
          <p className="page-subtitle">Comptes autorisés à accéder au portail central.</p>
        </div>
        <button type="button" onClick={() => setEdition({ role: "support", actif: true })} className="btn bg-ink-900 text-white">
          Ajouter un compte
        </button>
      </div>

      {erreur && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{erreur}</div>}

      <div className="overflow-x-auto card">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
            <tr>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">E-mail</th>
              <th className="px-4 py-3">Rôle</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3">Dernière activité</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {utilisateurs.map((utilisateur) => (
              <tr key={utilisateur.id} className="border-t border-ink-100">
                <td className="px-4 py-3 font-medium text-ink-800">
                  {utilisateur.name}{estMoi(utilisateur) && <span className="ml-2 text-xs text-ink-400">Vous</span>}
                </td>
                <td className="px-4 py-3">{utilisateur.email}</td>
                <td className="px-4 py-3">{ROLES.find((role) => role.value === utilisateur.role)?.label ?? utilisateur.role}</td>
                <td className="px-4 py-3">{utilisateur.actif ? "Actif" : "Désactivé"}</td>
                <td className="px-4 py-3 text-ink-500">
                  {utilisateur.tokens_max_last_used_at ? new Date(utilisateur.tokens_max_last_used_at).toLocaleString("fr-FR") : "—"}
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-3">
                    <button type="button" onClick={() => setEdition({ ...utilisateur, password: "" })} className="text-brand-800 hover:underline">
                      Modifier
                    </button>
                    <button
                      type="button"
                      disabled={estMoi(utilisateur) || (utilisateur.actif && utilisateur.role === "super_admin" && utilisateurs.filter((u) => u.actif && u.role === "super_admin").length === 1)}
                      onClick={() => changerActif(utilisateur)}
                      className="text-red-700 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                      title={estMoi(utilisateur) ? "Vous ne pouvez pas désactiver votre propre compte" : undefined}
                    >
                      {utilisateur.actif ? "Désactiver" : "Réactiver"}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {edition && (
        <Modal title={edition.id ? "Modifier un compte Auditron" : "Créer un compte Auditron"} onClose={() => setEdition(null)}>
          <form onSubmit={enregistrer} className="space-y-3">
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Nom</span>
              <input required maxLength={255} value={edition.name ?? ""} onChange={(event) => setEdition({ ...edition, name: event.target.value })} className="field w-full" />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">E-mail</span>
              <input required type="email" value={edition.email ?? ""} onChange={(event) => setEdition({ ...edition, email: event.target.value })} className="field w-full" />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Rôle</span>
              <select value={edition.role ?? "support"} onChange={(event) => setEdition({ ...edition, role: event.target.value })} className="field w-full">
                {ROLES.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">
                {edition.id ? "Nouveau mot de passe (facultatif)" : "Mot de passe (vide : généré automatiquement)"}
              </span>
              <input
                type="password"
                minLength={12}
                autoComplete="new-password"
                value={edition.password ?? ""}
                onChange={(event) => setEdition({ ...edition, password: event.target.value })}
                className="field w-full"
              />
              <span className="mt-1 block text-xs text-ink-500">Minimum 12 caractères.</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-ink-700">
              <input type="checkbox" checked={Boolean(edition.actif)} onChange={(event) => setEdition({ ...edition, actif: event.target.checked })} />
              Compte actif
            </label>
            <button type="submit" disabled={enCours} className="btn w-full bg-ink-900 text-white">
              {enCours ? "Enregistrement…" : "Enregistrer"}
            </button>
          </form>
        </Modal>
      )}

      {identifiants && (
        <Modal title="Compte créé" onClose={() => setIdentifiants(null)}>
          <p className="mb-3 text-sm text-ink-700">Transmettez ces identifiants à {identifiants.utilisateur.name}. Le mot de passe n’est affiché qu’une fois.</p>
          <dl className="space-y-1 rounded-lg bg-ink-50 p-3 font-mono text-sm">
            <div>{identifiants.utilisateur.email}</div>
            <div>{identifiants.password}</div>
          </dl>
        </Modal>
      )}
    </div>
  );
}
