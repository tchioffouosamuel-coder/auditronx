import { Navigate } from "react-router-dom";
import { usePlatformAuth } from "../context/PlatformAuthContext";
import LoadingState from "./LoadingState";

/** Réserve une route au personnel Auditron connecté au portail éditeur. */
export default function RoutePlateforme({ children }) {
  const { compte, chargement } = usePlatformAuth();

  if (chargement) return <LoadingState label="Vérification de la session" />;
  if (!compte) return <Navigate to="/plateforme/connexion" replace />;

  return children;
}
