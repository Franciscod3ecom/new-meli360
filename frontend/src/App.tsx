
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom'
import Inventory from './pages/Inventory'
import { api } from './services/api'
import { LayoutDashboard, LogIn, Package } from 'lucide-react'

const queryClient = new QueryClient()

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={import.meta.env.PROD ? '/meli360' : '/'}>
        <div className="min-h-screen bg-gray-50 text-gray-900 font-sans">

          {/* Header */}
          <header className="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <LayoutDashboard className="h-6 w-6 text-yellow-500" />
                <span className="text-xl font-bold tracking-tight">Meli360</span>
              </div>

              <nav className="flex items-center gap-4">
                <Link to="/" className="text-sm font-medium hover:text-yellow-600 transition-colors">
                  Dashboard
                </Link>
                <Link to="/inventory" className="text-sm font-medium hover:text-yellow-600 transition-colors">
                  Inventário
                </Link>
              </nav>

              <div className="flex items-center gap-2">
                <button
                  onClick={api.getAuthUrl}
                  className="flex items-center gap-2 px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-semibold rounded-md text-sm transition-colors"
                >
                  <LogIn className="h-4 w-4" />
                  Conectar ML
                </button>
              </div>
            </div>
          </header>

          {/* Main Content */}
          <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <Routes>
              <Route path="/" element={<Home />} />
              <Route path="/inventory" element={<Inventory />} />
            </Routes>
          </main>

        </div>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

function Home() {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center space-y-4">
      <div className="bg-yellow-100 p-4 rounded-full">
        <Package className="h-12 w-12 text-yellow-600" />
      </div>
      <h1 className="text-3xl font-bold text-gray-900">Bem-vindo ao Meli360</h1>
      <p className="text-gray-500 max-w-md">
        Gerencie seu inventário do Mercado Livre com inteligência. Identifique anúncios parados e otimize sua logística.
      </p>
      <div className="flex gap-4 pt-4">
        <Link
          to="/inventory"
          className="px-6 py-2 bg-gray-900 text-white rounded-md font-medium hover:bg-gray-800 transition-colors"
        >
          Acessar Inventário
        </Link>
      </div>
    </div>
  )
}

export default App
