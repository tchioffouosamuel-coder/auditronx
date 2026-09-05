import { BrowserRouter, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'
import { AuthProvider } from './context/AuthContext'
import AppLayout from './layouts/AppLayout'
import AccreditationsPage from './pages/AccreditationsPage'
import AlertesPage from './pages/AlertesPage'
import AppareilsPage from './pages/AppareilsPage'
import AssiduitePage from './pages/AssiduitePage'
import CahierTextePage from './pages/CahierTextePage'
import ClassesPage from './pages/ClassesPage'
import DashboardPage from './pages/DashboardPage'
import DisciplinesPage from './pages/DisciplinesPage'
import EmploisPage from './pages/EmploisPage'
import FeriesPage from './pages/FeriesPage'
import FicheProgressionPage from './pages/FicheProgressionPage'
import LoginPage from './pages/LoginPage'
import PersonnelPage from './pages/PersonnelPage'
import RetardsPage from './pages/RetardsPage'
import SignalementsPage from './pages/SignalementsPage'
import ValidationPage from './pages/ValidationPage'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

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
            <Route path="/fiche-progression" element={<FicheProgressionPage />} />
            <Route path="/appareils" element={<AppareilsPage />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
