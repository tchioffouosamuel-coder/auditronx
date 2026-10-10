import { createContext, useContext, useEffect, useState } from "react";
import apiCentral, {
  CLE_COMPTE_PLATEFORME,
  CLE_JETON_PLATEFORME,
} from "../lib/apiCentral";

const PlatformAuthContext = createContext(null);

/**
 * Session du portail éditeur, séparée de celle des établissements : un membre
 * du support peut être connecté aux deux en même temps sans que l'une chasse
 * l'autre.
 */
export function PlatformAuthProvider({ children }) {
  const [compte, setCompte] = useState(() => {
    const brut = localStorage.getItem(CLE_COMPTE_PLATEFORME);

    if (!brut) return null;

    try {
      return JSON.parse(brut);
    } catch {
      localStorage.removeItem(CLE_COMPTE_PLATEFORME);
      return null;
    }
  });
  const [chargement, setChargement] = useState(true);

  useEffect(() => {
    if (!localStorage.getItem(CLE_JETON_PLATEFORME)) {
      setChargement(false);
      return;
    }

    apiCentral
      .get("/me")
      .then(({ data }) => {
        setCompte(data);
        localStorage.setItem(CLE_COMPTE_PLATEFORME, JSON.stringify(data));
      })
      .catch(() => setCompte(null))
      .finally(() => setChargement(false));
  }, []);

  async function connexion(email, password) {
    const { data } = await apiCentral.post("/login", { email, password });

    localStorage.setItem(CLE_JETON_PLATEFORME, data.token);
    localStorage.setItem(CLE_COMPTE_PLATEFORME, JSON.stringify(data.user));
    setCompte(data.user);

    return data.user;
  }

  async function deconnexion() {
    try {
      await apiCentral.post("/logout");
    } finally {
      localStorage.removeItem(CLE_JETON_PLATEFORME);
      localStorage.removeItem(CLE_COMPTE_PLATEFORME);
      setCompte(null);
    }
  }

  // Le portail masque ce que l'API refuserait de toute façon : créer,
  // provisionner, facturer et supprimer sont réservés au super-administrateur.
  const estSuperAdmin = compte?.role === "super_admin";

  return (
    <PlatformAuthContext.Provider
      value={{ compte, chargement, connexion, deconnexion, estSuperAdmin }}
    >
      {children}
    </PlatformAuthContext.Provider>
  );
}

export function usePlatformAuth() {
  return useContext(PlatformAuthContext);
}
