import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import Dashboard from './features/analisador/pages/Dashboard'
import { Toaster } from 'sonner'
import './App.css'

const queryClient = new QueryClient()

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={import.meta.env.PROD ? '/meli360' : '/'}>
        <div className="min-h-screen bg-gray-50/50 p-6 font-sans text-gray-900">
          <div className="max-w-7xl mx-auto space-y-8">
            <header className="flex items-center justify-between pb-6 border-b border-gray-200">
              <h1 className="text-2xl font-bold tracking-tight text-gray-900">Meli360 <span className="text-gray-400 font-light">Analisador</span></h1>
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
      </BrowserRouter>
    </QueryClientProvider>
  )
}

export default App
