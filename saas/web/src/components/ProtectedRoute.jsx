import { Navigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import LoadingState from "./LoadingState";

export default function ProtectedRoute({ children, role = "backoffice" }) {
  const { user, loading, isEnseignant } = useAuth();

  if (loading) return <LoadingState label="Vérification de la session" />;
  if (!user) return <Navigate to="/login" replace />;
  if (role === "enseignant" && !isEnseignant)
    return <Navigate to="/" replace />;
  if (role === "backoffice" && isEnseignant)
    return <Navigate to="/enseignant" replace />;

  return children;
}
