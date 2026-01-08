import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import Inventory from './pages/Inventory'

const queryClient = new QueryClient()

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <div className="min-h-screen bg-background text-foreground font-sans antialiased">
        <Inventory />
      </div>
    </QueryClientProvider>
  )
}

export default App
