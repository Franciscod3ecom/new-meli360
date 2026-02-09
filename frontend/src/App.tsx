import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactNode, useState, useEffect } from 'react'
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
import { BarChart3, Package, Moon, Sun } from 'lucide-react'
import { Toaster } from 'sonner'
import { cn } from './lib/utils'
import './App.css'

const queryClient = new QueryClient()

function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme] = useState<'light' | 'dark'>(() => {
    const saved = localStorage.getItem('theme')
    return (saved as 'light' | 'dark') || 'dark'
  })

  useEffect(() => {
    const root = window.document.documentElement
    root.classList.remove('light', 'dark')
    root.classList.add(theme)
    localStorage.setItem('theme', theme)
  }, [theme])

  return (
    <div className="contents">
      {children}
    </div>
  )
}

function LicenseGate({ children }: { children: ReactNode }) {
  const { isLicenseValid, isCheckingLicense } = useLicense()

  if (isCheckingLicense) {
    return <div className="h-screen w-full flex items-center justify-center bg-neutral-0 dark:bg-neutral-950 text-neutral-500 text-sm italic">Verificando licença...</div>
  }

  if (!isLicenseValid) {
    return <LicenseActivation />
  }

  return <>{children}</>
}

function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) return <div className="h-screen flex items-center justify-center text-neutral-400">Carregando portal...</div>
  if (!isAuthenticated) return <Navigate to="/login" replace />

  return <>{children}</>
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <BrowserRouter basename={import.meta.env.PROD ? '/meli360' : '/'}>
          <Routes>
            {/* Rotas públicas - fora do LicenseGate */}
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword />} />

            {/* Rotas protegidas por licença */}
            <Route path="/*" element={
              <LicenseProvider>
                <LicenseGate>
                  <AuthProvider>
                    <Routes>
                      <Route path="/login" element={<Login />} />
                      <Route path="/register" element={<Register />} />

                      <Route path="/*" element={
                        <ProtectedRoute>
                          <AppLayout />
                        </ProtectedRoute>
                      } />
                    </Routes>
                  </AuthProvider>
                </LicenseGate>
              </LicenseProvider>
            } />
          </Routes>
          <Toaster position="bottom-right" richColors />
        </BrowserRouter>
      </ThemeProvider>
    </QueryClientProvider>
  )
}

function AppLayout() {
  const [theme, setTheme] = useState<'light' | 'dark'>(() => {
    return (document.documentElement.classList.contains('dark') ? 'dark' : 'light')
  })

  useEffect(() => {
    const observer = new MutationObserver(() => {
      setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light')
    })
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
    return () => observer.disconnect()
  }, [])

  const toggleTheme = () => {
    const root = window.document.documentElement
    const next = theme === 'light' ? 'dark' : 'light'
    root.classList.remove('light', 'dark')
    root.classList.add(next)
    localStorage.setItem('theme', next)
  }

  return (
    <div className="min-h-screen bg-neutral-0 dark:bg-neutral-950 p-6 font-sans text-neutral-900 transition-colors">
      <div className="max-w-7xl mx-auto space-y-8">
        <header className="pb-6 border-b border-neutral-100 dark:border-neutral-900">
          <div className="flex items-center justify-between mb-6">
            <h1 className="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-50">
              Meli360 <span className="text-neutral-400 font-light ml-1">Analisador</span>
            </h1>
            <div className="flex items-center gap-3">
              <button
                onClick={toggleTheme}
                className="p-2.5 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl text-neutral-500 hover:text-brand-500 transition-all"
                title="Alternar Tema"
              >
                {theme === 'light' ? <Moon className="w-5 h-5" /> : <Sun className="w-5 h-5" />}
              </button>
              <AccountSwitcher />
              <div className="h-6 w-px bg-neutral-200 dark:bg-neutral-800 mx-1" />
              <LogoutButton />
            </div>
          </div>
          <Navigation />
        </header>

        <main className="animate-fade-in">
          <OnboardingGate>
            <Routes>
              <Route path="/" element={<Navigate to="/inventory" replace />} />
              <Route path="/inventory" element={<Dashboard />} />
              <Route path="/analytics" element={<AnalyticsDashboard />} />
              <Route path="/forgot-password" element={<ForgotPassword />} />
              <Route path="/reset-password" element={<ResetPassword />} />
            </Routes>
          </OnboardingGate>
        </main>
      </div>
      <SyncStatus />
    </div>
  )
}

function Navigation() {
  const location = useLocation()

  const links = [
    { path: '/inventory', label: 'Inventário', icon: Package },
    { path: '/analytics', label: 'Análise de Frete', icon: BarChart3 }
  ]

  return (
    <nav className="flex gap-1.5 p-1 bg-neutral-100/50 dark:bg-neutral-900/50 rounded-2xl w-fit backdrop-blur-sm border border-neutral-200/50 dark:border-neutral-800/50">
      {links.map(link => {
        const Icon = link.icon
        const isActive = location.pathname === link.path
        return (
          <Link
            key={link.path}
            to={link.path}
            className={cn(
              "flex items-center gap-2.5 px-5 py-2.5 rounded-xl font-medium transition-all duration-300 relative group",
              isActive
                ? "bg-brand-500 text-neutral-900 shadow-lg shadow-brand-500/20"
                : "text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 hover:bg-white dark:hover:bg-neutral-800 shadow-sm hover:shadow-md"
            )}
          >
            <Icon className={cn("w-4 h-4 transition-transform duration-300 group-hover:scale-110", isActive ? "text-neutral-900" : "text-neutral-400")} />
            {link.label}
          </Link>
        )
      })}
    </nav>
  )
}

export default App
