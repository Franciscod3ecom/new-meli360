import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactNode } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import Dashboard from './features/analisador/pages/Dashboard'
import Login from './features/analisador/pages/Login'
import { AccountSwitcher } from './features/analisador/components/AccountSwitcher'
import { Toaster } from 'sonner'
import './App.css'

const queryClient = new QueryClient()

function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) return <div className="h-screen flex items-center justify-center text-gray-400">Carregando...</div>
  if (!isAuthenticated) return <Navigate to="/login" replace />

  return <>{children}</>
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter basename={import.meta.env.PROD ? '/meli360' : '/'}>
          <Routes>
            <Route path="/login" element={<Login />} />

            <Route path="/*" element={
              <ProtectedRoute>
                <div className="min-h-screen bg-gray-50/50 p-6 font-sans text-gray-900">
                  <div className="max-w-7xl mx-auto space-y-8">
                    <header className="flex items-center justify-between pb-6 border-b border-gray-200">
                      <h1 className="text-2xl font-bold tracking-tight text-gray-900">Meli360 <span className="text-gray-400 font-light">Analisador</span></h1>
                      <div className="flex items-center gap-4">
                        <AccountSwitcher />
                      </div>
                    </header>

                    <main>
                      <Routes>
                        <Route path="/" element={<Navigate to="/inventory" replace />} />
                        <Route path="/inventory" element={<Dashboard />} />
                      </Routes>
                    </main>
                  </div>
                  <Toaster />
                </div>
              </ProtectedRoute>
            } />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  )
}

export default App
