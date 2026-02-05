import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactNode } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { LicenseProvider, useLicense } from './context/LicenseContext'
import { AuthProvider, useAuth } from './context/AuthContext'
import Dashboard from './features/analisador/pages/Dashboard'
import AnalyticsDashboard from './features/analisador/pages/AnalyticsDashboard'
import Login from './features/analisador/pages/Login'
import Register from './features/analisador/pages/Register'
import ForgotPassword from './features/auth/ForgotPassword'
import ResetPassword from './features/auth/ResetPassword'
import LicenseActivation from './features/analisador/pages/LicenseActivation'
import { AccountSwitcher } from './features/analisador/components/AccountSwitcher'
import { LogoutButton } from './features/analisador/components/LogoutButton'
import { SyncStatus } from './features/analisador/components/SyncStatus'
import { OnboardingGate } from './features/analisador/components/OnboardingGate'
import { Link, useLocation } from 'react-router-dom'
import { BarChart3, Package } from 'lucide-react'
import { Toaster } from 'sonner'
import { cn } from './lib/utils'
import './App.css'

const queryClient = new QueryClient()

function LicenseGate({ children }: { children: ReactNode }) {
  const { isLicenseValid, isCheckingLicense } = useLicense()

  if (isCheckingLicense) {
    return <div className="h-screen w-full flex items-center justify-center bg-white text-gray-500 text-sm">Verificando licença...</div>
  }

  if (!isLicenseValid) {
    return <LicenseActivation />
  }

  return <>{children}</>
}

function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) return <div className="h-screen flex items-center justify-center text-gray-400">Carregando...</div>
  if (!isAuthenticated) return <Navigate to="/login" replace />

  return <>{children}</>
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <LicenseProvider>
        <LicenseGate>
          <AuthProvider>
            <BrowserRouter basename={import.meta.env.PROD ? '/meli360' : '/'}>
              <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route path="/forgot-password" element={<ForgotPassword />} />
                <Route path="/reset-password" element={<ResetPassword />} />

                <Route path="/*" element={
                  <ProtectedRoute>
                    <div className="min-h-screen bg-gray-50/50 p-6 font-sans text-gray-900">
                      <div className="max-w-7xl mx-auto space-y-8">
                        <header className="pb-6 border-b border-gray-200">
                          <div className="flex items-center justify-between mb-4">
                            <h1 className="text-2xl font-bold tracking-tight text-gray-900">Meli360 <span className="text-gray-400 font-light">Analisador</span></h1>
                            <div className="flex items-center gap-2">
                              <AccountSwitcher />
                              <div className="h-6 w-px bg-gray-200 mx-2" /> {/* Divider */}
                              <LogoutButton />
                            </div>
                          </div>
                          <Navigation />
                        </header>

                        <main>
                          <OnboardingGate>
                            <Routes>
                              <Route path="/" element={<Navigate to="/inventory" replace />} />
                              <Route path="/inventory" element={<Dashboard />} />
                              <Route path="/analytics" element={<AnalyticsDashboard />} />
                            </Routes>
                          </OnboardingGate>
                        </main>
                      </div>
                      <SyncStatus />
                      <Toaster />
                    </div>
                  </ProtectedRoute>
                } />
              </Routes>
            </BrowserRouter>
          </AuthProvider>
        </LicenseGate>
      </LicenseProvider>
    </QueryClientProvider>
  )
}

function Navigation() {
  const location = useLocation()

  const links = [
    { path: '/inventory', label: 'Inventário', icon: Package },
    { path: '/analytics', label: 'Análise', icon: BarChart3 }
  ]

  return (
    <nav className="flex gap-2">
      {links.map(link => {
        const Icon = link.icon
        const isActive = location.pathname === link.path
        return (
          <Link
            key={link.path}
            to={link.path}
            className={cn(
              "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all",
              isActive
                ? "bg-yellow-600 text-white shadow-md"
                : "bg-white text-gray-600 hover:bg-gray-100 border border-gray-200"
            )}
          >
            <Icon className="w-4 h-4" />
            {link.label}
          </Link>
        )
      })}
    </nav>
  )
}

export default App
