import { useEffect, useState } from "react";
import LoadingState from "../../components/LoadingState";
import Modal from "../../components/Modal";
import { usePlatformAuth } from "../../context/PlatformAuthContext";
import apiCentral from "../../lib/apiCentral";

const STATUTS = ["en_attente", "actif", "suspendu", "archive"];

const CHAMPS_CREATION = [
  { key: "code", label: "Code (en-tête X-Tenant, suffixe de base)", requis: true },
  { key: "nom", label: "Nom complet", requis: true },
  { key: "nom_court", label: "Nom court (app mobile)" },
  { key: "ville", label: "Ville" },
  { key: "couleur_primaire", label: "Couleur primaire (#0F766E)" },
  { key: "contact_nom", label: "Contact — nom" },
  { key: "contact_email", label: "Contact — email", type: "email" },
  { key: "contact_tel", label: "Contact — téléphone" },
  { key: "direction_email", label: "E-mail du compte de direction à créer", type: "email" },
];

export default function EtablissementsPage() {
  const { estSuperAdmin } = usePlatformAuth();
  const [etablissements, setEtablissements] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [creation, setCreation] = useState(null);
  const [identifiants, setIdentifiants] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [suppression, setSuppression] = useState(null);
  const [confirmation, setConfirmation] = useState("");

  function recharger() {
    return apiCentral
      .get("/etablissements")
      .then(({ data }) => setEtablissements(data.data))
      .catch(() => setErreur("Chargement impossible."));
  }

  useEffect(() => {
    recharger();
  }, []);

  async function creer(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);

    try {
      const { data } = await apiCentral.post("/etablissements", creation);
      setCreation(null);
      // Mot de passe affiché une seule fois : il n'est stocké que haché dans
      // la base du client, personne ne pourra le relire ensuite.
      setIdentifiants({ ...data.identifiants_direction, code: data.data.code });
      await recharger();
    } catch (e) {
      setErreur(
        e.response?.data?.message ?? "Création impossible : vérifiez le code et le nom.",
      );
    } finally {
      setEnCours(false);
    }
  }

  async function changerStatut(etablissement, statut) {
    await apiCentral.post(`/etablissements/${etablissement.id}/statut`, { statut });
    await recharger();
  }

  async function provisionner(etablissement) {
    setEnCours(true);
    try {
      const { data } = await apiCentral.post(`/etablissements/${etablissement.id}/provision`, {});
      setIdentifiants({ ...data.identifiants_direction, code: data.data.code });
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Provisioning impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function migrer(etablissement) {
    setEnCours(true);
    try {
      await apiCentral.post(`/etablissements/${etablissement.id}/migrate`, {});
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Migrations impossibles.");
    } finally {
      setEnCours(false);
    }
  }

  async function supprimer(e) {
    e.preventDefault();
    setEnCours(true);

    try {
      await apiCentral.delete(`/etablissements/${suppression.id}`, {
        data: { confirmation },
      });
      setSuppression(null);
      setConfirmation("");
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Suppression refusée.");
    } finally {
      setEnCours(false);
    }
  }

  if (!etablissements) return <LoadingState />;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="page-title">
          Établissements abonnés ({etablissements.length})
        </h1>

        {estSuperAdmin && (
          <button
            type="button"
            onClick={() => setCreation({})}
            className="btn bg-ink-900 text-white hover:bg-ink-800"
          >
            Ouvrir un établissement
          </button>
        )}
      </div>

      {erreur && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erreur}</div>
      )}

      <div className="overflow-x-auto card">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
            <tr>
              <th className="px-4 py-2">Établissement</th>
              <th className="px-4 py-2">Code</th>
              <th className="px-4 py-2">Statut</th>
              <th className="px-4 py-2">Plan</th>
              <th className="px-4 py-2">Échéance</th>
              {estSuperAdmin && <th className="px-4 py-2">Actions</th>}
            </tr>
          </thead>
          <tbody>
            {etablissements.map((etablissement) => (
              <tr key={etablissement.id} className="border-t border-ink-100">
                <td className="px-4 py-2">
                  <span className="font-medium text-ink-800">{etablissement.nom}</span>
                  {etablissement.ville && (
                    <span className="ml-2 text-xs text-ink-400">{etablissement.ville}</span>
                  )}
                </td>
                <td className="px-4 py-2 font-mono text-xs">{etablissement.code}</td>
                <td className="px-4 py-2">
                  {estSuperAdmin ? (
                    <select
                      value={etablissement.statut}
                      onChange={(e) => changerStatut(etablissement, e.target.value)}
                      className="rounded border border-ink-100 px-2 py-1 text-xs"
                    >
                      {STATUTS.map((statut) => (
                        <option key={statut} value={statut}>
                          {statut}
                        </option>
                      ))}
                    </select>
                  ) : (
                    etablissement.statut
                  )}
                </td>
                <td className="px-4 py-2">{etablissement.abonnement_actif?.plan?.nom ?? "—"}</td>
                <td className="px-4 py-2 text-ink-500">
                  {etablissement.abonnement_actif?.fin_le ?? "sans échéance"}
                </td>
                {estSuperAdmin && (
                  <td className="px-4 py-2">
                    <div className="flex flex-wrap gap-2 text-xs">
                      {!etablissement.provisionne_le && (
                        <button
                          type="button"
                          disabled={enCours}
                          onClick={() => provisionner(etablissement)}
                          className="rounded border border-brand-200 px-2 py-1 text-brand-800 hover:bg-brand-50"
                        >
                          Provisionner
                        </button>
                      )}
                      <button
                        type="button"
                        disabled={enCours}
                        onClick={() => migrer(etablissement)}
                        className="btn-secondary px-2 py-1 text-xs"
                      >
                        Migrer
                      </button>
                      <button
                        type="button"
                        onClick={() => setSuppression(etablissement)}
                        className="rounded border border-red-200 px-2 py-1 text-red-700 hover:bg-red-50"
                      >
                        Supprimer
                      </button>
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {creation && (
        <Modal title="Ouvrir un établissement" onClose={() => setCreation(null)}>
          <form onSubmit={creer} className="space-y-3">
            {CHAMPS_CREATION.map((champ) => (
              <label key={champ.key} className="block text-sm">
                <span className="mb-1 block font-medium text-ink-700">{champ.label}</span>
                <input
                  type={champ.type ?? "text"}
                  required={champ.requis}
                  value={creation[champ.key] ?? ""}
                  onChange={(e) => setCreation({ ...creation, [champ.key]: e.target.value })}
                  className="field w-full"
                />
              </label>
            ))}

            <p className="text-xs text-ink-500">
              La base de l’établissement est créée et migrée immédiatement, et un compte de
              direction y est amorcé.
            </p>

            <button
              type="submit"
              disabled={enCours}
              className="btn w-full bg-ink-900 text-white hover:bg-ink-800"
            >
              {enCours ? "Provisioning…" : "Créer et provisionner"}
            </button>
          </form>
        </Modal>
      )}

      {identifiants && (
        <Modal title="Identifiants à transmettre" onClose={() => setIdentifiants(null)}>
          <p className="mb-3 text-sm text-ink-700">
            Compte de direction de <strong>{identifiants.code}</strong>. Le mot de passe n’est
            affiché qu’une fois : il n’est conservé que haché.
          </p>
          <dl className="space-y-1 rounded-lg bg-ink-50 p-3 font-mono text-sm">
            <div>{identifiants.email}</div>
            <div>{identifiants.password}</div>
          </dl>
        </Modal>
      )}

      {suppression && (
        <Modal
          title={`Supprimer ${suppression.code}`}
          onClose={() => {
            setSuppression(null);
            setConfirmation("");
          }}
        >
          <form onSubmit={supprimer} className="space-y-3">
            <p className="text-sm text-red-700">
              Cette action supprime la base de données et les fichiers de{" "}
              <strong>{suppression.nom}</strong>. Elle est irréversible. Pour une suspension
              d’abonnement, utilisez plutôt le statut « suspendu ».
            </p>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">
                Retapez le code {suppression.code} pour confirmer
              </span>
              <input
                type="text"
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
                className="field w-full uppercase"
              />
            </label>

            <button
              type="submit"
              disabled={enCours || confirmation.toUpperCase() !== suppression.code}
              className="btn w-full bg-red-700 text-white hover:bg-red-800"
            >
              Supprimer définitivement
            </button>
          </form>
        </Modal>
      )}
    </div>
  );
}
