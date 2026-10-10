import { BrowserRouter, Route, Routes } from "react-router-dom";
import ProtectedRoute from "./components/ProtectedRoute";
import RoutePlateforme from "./components/RoutePlateforme";
import { AuthProvider } from "./context/AuthContext";
import { PlatformAuthProvider } from "./context/PlatformAuthContext";
import { TenantProvider } from "./context/TenantContext";
import AppLayout from "./layouts/AppLayout";
import PlateformeLayout from "./layouts/PlateformeLayout";
import AccreditationsPage from "./pages/AccreditationsPage";
import AlertesPage from "./pages/AlertesPage";
import AppareilsPage from "./pages/AppareilsPage";
import AssiduitePage from "./pages/AssiduitePage";
import CahierTextePage from "./pages/CahierTextePage";
import ClassesPage from "./pages/ClassesPage";
import ConfigurationPage from "./pages/ConfigurationPage";
import DashboardPage from "./pages/DashboardPage";
import DisciplinesPage from "./pages/DisciplinesPage";
import EmploisPage from "./pages/EmploisPage";
import FeriesPage from "./pages/FeriesPage";
import FicheProgressionPage from "./pages/FicheProgressionPage";
import FirmwaresPage from "./pages/FirmwaresPage";
import JournalAuditPage from "./pages/JournalAuditPage";
import LoginPage from "./pages/LoginPage";
import MoniteurBornesPage from "./pages/MoniteurBornesPage";
import PersonnelPage from "./pages/PersonnelPage";
import RetardsPage from "./pages/RetardsPage";
import SignalementsPage from "./pages/SignalementsPage";
import ConnexionPlateformePage from "./pages/plateforme/ConnexionPage";
import EtablissementsPage from "./pages/plateforme/EtablissementsPage";
import FacturationPage from "./pages/plateforme/FacturationPage";
import TableauDeBordPlateformePage from "./pages/plateforme/TableauDeBordPage";
import TeacherPortalPage from "./pages/TeacherPortalPage";
import ValidationPage from "./pages/ValidationPage";

export default function App() {
  return (
    <BrowserRouter>
      {/*
        Deux mondes dans un seul portail :

        - `/plateforme/*` : l'espace de l'éditeur, sur la base centrale ;
        - tout le reste : l'espace d'un établissement, désigné par le code
          mémorisé dans TenantProvider et envoyé en en-tête `X-Tenant`.

        TenantProvider englobe AuthProvider parce que la session dépend de
        l'établissement : un jeton n'a de sens que dans la base où il a été
        émis.
      */}
      <TenantProvider>
      <PlatformAuthProvider>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route path="/plateforme/connexion" element={<ConnexionPlateformePage />} />
          <Route
            path="/plateforme"
            element={
              <RoutePlateforme>
                <PlateformeLayout />
              </RoutePlateforme>
            }
          >
            <Route index element={<TableauDeBordPlateformePage />} />
            <Route path="etablissements" element={<EtablissementsPage />} />
            <Route path="facturation" element={<FacturationPage />} />
          </Route>
          <Route
            path="/enseignant"
            element={
              <ProtectedRoute role="enseignant">
                <TeacherPortalPage />
              </ProtectedRoute>
            }
          />

          <Route
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route path="/" element={<DashboardPage />} />
            <Route path="/personnel" element={<PersonnelPage />} />
            <Route path="/classes" element={<ClassesPage />} />
            <Route path="/disciplines" element={<DisciplinesPage />} />
            <Route path="/emplois" element={<EmploisPage />} />
            <Route path="/accreditations" element={<AccreditationsPage />} />
            <Route path="/retards" element={<RetardsPage />} />
            <Route path="/assiduite" element={<AssiduitePage />} />
            <Route path="/validation" element={<ValidationPage />} />
            <Route path="/signalements" element={<SignalementsPage />} />
            <Route path="/feries" element={<FeriesPage />} />
            <Route path="/alertes" element={<AlertesPage />} />
            <Route path="/cahier-texte" element={<CahierTextePage />} />
            <Route
              path="/fiche-progression"
              element={<FicheProgressionPage />}
            />
            <Route path="/appareils" element={<AppareilsPage />} />
            <Route path="/moniteur-bornes" element={<MoniteurBornesPage />} />
            <Route path="/firmwares" element={<FirmwaresPage />} />
            <Route path="/journal-audit" element={<JournalAuditPage />} />
            <Route path="/configuration" element={<ConfigurationPage />} />
          </Route>
        </Routes>
      </AuthProvider>
      </PlatformAuthProvider>
      </TenantProvider>
    </BrowserRouter>
  );
}
