
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { supabase } from '../lib/supabase'
import { api } from '../services/api'
import {
  Zap,
  RefreshCw,
  Play,
  Pause,
  CheckSquare,
  Square,
  Eye,
  ShoppingBag
} from 'lucide-react'
import { cn } from '../lib/utils'

// Data Types based on Schema
interface Item {
  id: string
  ml_id: string
  title: string
  price: number
  status: string
  permalink: string
  thumbnail: string
  sold_quantity: number
  available_quantity: number
  shipping_mode: string
  logistic_type: string
  free_shipping: boolean
  last_sale_date: string | null
  date_created: string
  total_visits: number
  days_without_sale?: number // From View or Calculated
  health?: number // 0 to 1
}

export default function Inventory() {
  const [filter, setFilter] = useState<'all' | 'full' | 'active' | 'paused' | 'no_stock' | 'closed'>('all')
  const [salesFilter, setSalesFilter] = useState<'all' | 'never_sold' | 'over_30' | 'over_60' | 'over_90'>('all')
  const [isSyncing, setIsSyncing] = useState(false)
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set())
  const [isBulkUpdating, setIsBulkUpdating] = useState(false)

  // Query Data
  const { data: items, isLoading, error, refetch } = useQuery({
    queryKey: ['items'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('items')
        .select('*')
        .order('last_sale_date', { ascending: true, nullsFirst: false }) // Sort by older sales first
        .limit(200) // Increase limit for better analysis

      if (error) throw error

      return data.map((item: any) => {
        let days = 0;
        const now = new Date().getTime();

        if (item.last_sale_date) {
          const last = new Date(item.last_sale_date).getTime();
          days = Math.floor((now - last) / (1000 * 3600 * 24));
        } else if (item.date_created && item.sold_quantity === 0) {
          // If never sold, calculate days since creation
          const created = new Date(item.date_created).getTime();
          days = Math.floor((now - created) / (1000 * 3600 * 24));
        }
        return { ...item, days_without_sale: days } as Item
      })
    }
  })

  // Filter Logic
  const filteredItems = items?.filter(item => {
    // Status Filter
    if (filter === 'full' && item.logistic_type !== 'fulfillment') return false
    if (filter === 'active' && item.status !== 'active') return false
    if (filter === 'paused' && item.status !== 'paused') return false
    if (filter === 'no_stock' && item.available_quantity > 0) return false
    if (filter === 'closed' && item.status !== 'closed') return false

    // Sales/Time Filter
    if (salesFilter === 'never_sold' && item.sold_quantity > 0) return false
    if (salesFilter === 'over_30' && (item.days_without_sale || 0) < 30) return false
    if (salesFilter === 'over_60' && (item.days_without_sale || 0) < 60) return false
    if (salesFilter === 'over_90' && (item.days_without_sale || 0) < 90) return false

    return true
  })

  // Sync Handler
  const handleSync = async () => {
    setIsSyncing(true)
    const success = await api.triggerSync()
    if (success) {
      setTimeout(() => refetch(), 2000)
    }
    setIsSyncing(false)
  }

  // Bulk Selection Logic
  const toggleSelection = (id: string) => {
    const newSelected = new Set(selectedItems)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedItems(newSelected)
  }

  const toggleSelectAll = () => {
    if (selectedItems.size === filteredItems?.length && filteredItems?.length > 0) {
      setSelectedItems(new Set())
    } else {
      const allIds = filteredItems?.map(i => i.ml_id) || []
      setSelectedItems(new Set(allIds))
    }
  }

  // Bulk Action Logic
  const handleBulkUpdate = async (action: 'paused' | 'active') => {
    if (selectedItems.size === 0) return
    if (!confirm(`Tem certeza que deseja ${action === 'paused' ? 'PAUSAR' : 'ATIVAR'} ${selectedItems.size} itens?`)) return

    setIsBulkUpdating(true)
    try {
      const idsArray = Array.from(selectedItems)
      await api.bulkUpdate(idsArray, action)

      setSelectedItems(new Set())
      refetch()
      alert('Ação realizada com sucesso! Os status serão atualizados em instantes.')
    } catch (e) {
      alert('Erro ao realizar ação em massa. Tente novamente.')
      console.error(e)
    } finally {
      setIsBulkUpdating(false)
    }
  }

  // Helper for Badges
  const getDaysWithoutSaleBadge = (item: Item) => {
    if (item.sold_quantity === 0) {
      return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Nunca Vendeu</span>
    }

    const days = item.days_without_sale || 0;
    if (days > 90) return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Sem venda +90d</span>
    if (days > 60) return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">Sem venda +60d</span>
    if (days > 30) return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Sem venda +30d</span>

    return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Venda Recente</span>
  }

  if (isLoading) return <div className="p-8 text-center flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
  if (error) return (
    <div className="p-8 text-center text-red-500">
      <p className="font-bold">Erro ao carregar dados:</p>
      <p className="text-sm font-mono mt-2">{(error as any).message || JSON.stringify(error)}</p>
    </div>
  )

  return (
    <div className="space-y-6">

      {/* Controls */}
      <div className="flex flex-col xl:flex-row gap-4 justify-between items-start xl:items-center bg-white p-4 rounded-lg shadow-sm border border-gray-100">

        {/* Filters Group */}
        <div className="flex flex-col sm:flex-row gap-4 w-full xl:w-auto">

          {/* Status Filters */}
          <div className="flex flex-wrap gap-2">
            <FilterButton
              active={filter === 'all'}
              onClick={() => setFilter('all')}
              label="Todos"
            />
            <FilterButton
              active={filter === 'active'}
              onClick={() => setFilter('active')}
              label="Ativos"
              className="text-green-700 bg-green-50"
            />
            <FilterButton
              active={filter === 'paused'}
              onClick={() => setFilter('paused')}
              label="Pausados"
              className="text-yellow-700 bg-yellow-50"
            />
            <FilterButton
              active={filter === 'full'}
              onClick={() => setFilter('full')}
              label="Full"
              icon={<Zap className="w-3 h-3" />}
            />
          </div>

          <div className="h-px w-full sm:w-px sm:h-auto bg-gray-200"></div>

          {/* Sales Logic Filter */}
          <select
            title="Filtrar por último período de venda"
            value={salesFilter}
            onChange={(e) => setSalesFilter(e.target.value as any)}
            className="block w-full sm:w-48 pl-3 pr-10 py-1.5 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
          >
            <option value="all">Todas as Vendas</option>
            <option value="never_sold">Nunca Vendeu</option>
            <option value="over_30">Sem venda +30 dias</option>
            <option value="over_60">Sem venda +60 dias</option>
            <option value="over_90">Sem venda +90 dias</option>
          </select>
        </div>

        {/* Sync Action */}
        <button
          onClick={handleSync}
          disabled={isSyncing}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-all font-medium text-sm w-full sm:w-auto justify-center"
        >
          <RefreshCw className={cn("w-4 h-4", isSyncing && "animate-spin")} />
          {isSyncing ? 'Sincronizando...' : 'Sincronizar Agora'}
        </button>
      </div>



      {/* Bulk Action Bar - Only Visible when items selected */}
      {
        selectedItems.size > 0 && (
          <div className="fixed bottom-6 left-0 right-0 mx-auto w-max z-50 animate-in fade-in slide-in-from-bottom-4 duration-300">
            <div className="bg-gray-900 text-white px-6 py-3 rounded-full shadow-xl flex items-center gap-6 border border-gray-800">
              <span className="font-medium text-sm">{selectedItems.size} selecionados</span>

              <div className="h-4 w-px bg-gray-700" />

              <div className="flex gap-2">
                <button
                  onClick={() => handleBulkUpdate('paused')}
                  disabled={isBulkUpdating}
                  className="flex items-center gap-2 px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 rounded-md text-xs font-bold uppercase tracking-wider transition-colors disabled:opacity-50"
                >
                  <Pause className="w-3 h-3" /> Pausar
                </button>

                <button
                  onClick={() => handleBulkUpdate('active')}
                  disabled={isBulkUpdating}
                  className="flex items-center gap-2 px-3 py-1.5 bg-green-600 hover:bg-green-700 rounded-md text-xs font-bold uppercase tracking-wider transition-colors disabled:opacity-50"
                >
                  <Play className="w-3 h-3" /> Ativar
                </button>
              </div>

              {isBulkUpdating && <RefreshCw className="w-4 h-4 animate-spin text-gray-400" />}
            </div>
          </div>
        )
      }

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 border-b border-gray-200 text-gray-500 font-medium uppercase text-xs">
              <tr>
                <th className="px-4 py-3 w-10">
                  <button onClick={toggleSelectAll} className="flex items-center justify-center text-gray-400 hover:text-gray-600">
                    {(filteredItems?.length || 0) > 0 && selectedItems.size === (filteredItems?.length || 0) ? <CheckSquare className="w-5 h-5" /> : <Square className="w-5 h-5" />}
                  </button>
                </th>
                <th className="px-4 py-3 w-16">Foto</th>
                <th className="px-4 py-3 min-w-[200px]">Anúncio</th>
                <th className="px-4 py-3 w-28 text-center text-xs">Criação</th>
                <th className="px-4 py-3 w-20 text-center text-xs">Saúde</th>
                <th className="px-4 py-3 w-24 text-center text-xs">Visitas</th>
                <th className="px-4 py-3 w-24 text-center text-xs">Vendas</th>
                <th className="px-4 py-3 w-32 text-center text-xs">Última Venda</th>
                <th className="px-4 py-3 w-32 text-center text-xs">Tag</th>
                <th className="px-4 py-3 w-24 text-right">Preço</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filteredItems?.map(item => (
                <tr key={item.id} className={cn("transition-colors hover:bg-gray-50", selectedItems.has(item.ml_id) && "bg-blue-50/50 border-blue-200")}>
                  <td className="px-4 py-3">
                    <button onClick={() => toggleSelection(item.ml_id)} className={cn("flex items-center justify-center", selectedItems.has(item.ml_id) ? "text-blue-600" : "text-gray-300 hover:text-gray-500")}>
                      {selectedItems.has(item.ml_id) ? <CheckSquare className="w-5 h-5" /> : <Square className="w-5 h-5" />}
                    </button>
                  </td>
                  <td className="px-4 py-3">
                    <img src={item.thumbnail} alt="" className="w-12 h-12 object-cover rounded-md border border-gray-200" />
                  </td>
                  <td className="px-4 py-3">
                    <a href={item.permalink} target="_blank" rel="noreferrer" className="font-medium text-gray-900 hover:underline line-clamp-2 leading-snug" title={item.title}>
                      {item.title}
                    </a>
                    <div className="text-xs text-gray-500 mt-1 flex flex-wrap gap-2 items-center">
                      <span className="font-mono text-[10px] text-gray-400">{item.ml_id}</span>
                      <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase",
                        item.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                      )}>
                        {item.status}
                      </span>
                      {item.logistic_type === 'fulfillment' && (
                        <span className="flex items-center gap-0.5 text-[10px] bg-yellow-100 text-yellow-800 px-1 rounded font-bold"><Zap className="w-2 h-2" /> Full</span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-center text-gray-500 text-xs">
                    {item.date_created ? new Date(item.date_created).toLocaleDateString() : '-'}
                  </td>
                  <td className="px-4 py-3 text-center">
                    <div className="flex flex-col items-center gap-1">
                      <span className={cn("text-xs font-bold",
                        (item.health || 0) >= 0.8 ? "text-green-600" :
                          (item.health || 0) >= 0.6 ? "text-blue-600" :
                            (item.health || 0) >= 0.4 ? "text-yellow-600" : "text-red-600"
                      )}>
                        {Math.round((item.health || 0) * 100)}%
                      </span>
                      <div className="w-12 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div
                          className={cn("h-full rounded-full",
                            (item.health || 0) >= 0.8 ? "bg-green-500" :
                              (item.health || 0) >= 0.6 ? "bg-blue-500" :
                                (item.health || 0) >= 0.4 ? "bg-yellow-500" : "bg-red-500"
                          )}
                          style={{ width: `${Math.round((item.health || 0) * 100)}%` }}
                        />
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <div className="flex items-center justify-center gap-1 text-gray-700">
                      <Eye className="w-3 h-3 text-gray-400" />
                      {Number(item.total_visits || 0).toLocaleString()}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <div className="font-semibold text-gray-900">{item.sold_quantity}</div>
                  </td>
                  <td className="px-4 py-3 text-center text-xs">
                    <div className="flex flex-col items-center">
                      <span className="text-gray-900 font-medium">
                        {item.last_sale_date ? new Date(item.last_sale_date).toLocaleDateString() : '-'}
                      </span>
                      {item.last_sale_date && (
                        <span className="text-[9px] text-gray-400">
                          {new Date(item.last_sale_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      )}
                      <span className="text-[10px] text-gray-500 mt-0.5">
                        {(item.days_without_sale || 0) > 0 ? `${item.days_without_sale} dias s/ venda` : 'Vendeu Hoje'}
                      </span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-center">
                    {getDaysWithoutSaleBadge(item)}
                  </td>
                  <td className="px-4 py-3 text-right font-medium text-gray-900">
                    R$ {Number(item.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                  </td>
                </tr>
              ))}
              {filteredItems?.length === 0 && (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-gray-400">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <ShoppingBag className="w-8 h-8 text-gray-300" />
                      Nenhum item encontrado com estes filtros.
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="px-4 py-3 border-t border-gray-200 bg-gray-50 text-xs text-gray-500 flex justify-between items-center">
          <span>Mostrando {filteredItems?.length} de {items?.length} itens carregados.</span>
          <span className="text-gray-400 italic">Ordenado por última venda (antigas primeiro)</span>
        </div>
      </div>

    </div >
  )
}

function FilterButton({ active, onClick, label, icon, className }: any) {
  return (
    <button
      onClick={onClick}
      className={cn(
        "px-3 py-1.5 rounded-md text-sm font-medium border transition-all flex items-center gap-2 outline-none focus:ring-2 focus:ring-blue-500/20",
        active
          ? "bg-gray-900 text-white border-gray-900 shadow-sm"
          : "bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300",
        className
      )}
    >
      {icon}
      {label}
    </button>
  )
}
