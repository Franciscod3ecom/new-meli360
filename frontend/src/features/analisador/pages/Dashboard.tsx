
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import { SyncStatus } from '../components/SyncStatus'
import { Download, Pause } from 'lucide-react'
import { cn } from '../../../lib/utils'

// Data Types
interface Item {
    id: string
    ml_id: string
    title: string
    price: number
    status: string
    permalink: string
    thumbnail: string
    secure_thumbnail?: string
    health?: number
    total_visits?: number
    original_price?: number
    currency_id?: string
    sold_quantity: number
    available_quantity: number
    shipping_mode: string
    logistic_type: string
    free_shipping: boolean
    last_sale_date: string | null
    date_created: string
    days_without_sale?: number
    category_name?: string
    shipping_cost_nacional?: number
    billable_weight?: number
    weight_status?: string
}

export default function Dashboard() {
    const { user } = useAuth()

    // State
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'paused' | 'no_stock' | 'closed'>('all')
    const [salesFilter, setSalesFilter] = useState<'all' | 'never_sold' | 'over_30' | 'over_60' | 'over_90'>('all')
    const [currentPage, setCurrentPage] = useState(1)
    const [itemsPerPage, setItemsPerPage] = useState(100)
    const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set())
    const [isBulkPausing, setIsBulkPausing] = useState(false)
    const [alertMessage, setAlertMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

    // Query Data
    const { data: response, isLoading, error, refetch } = useQuery({
        queryKey: ['items', user?.id, currentPage, itemsPerPage, statusFilter, salesFilter],
        queryFn: async () => {
            if (!user?.id) return null
            return await api.getItems({
                page: currentPage,
                limit: itemsPerPage,
                status_filter: statusFilter,
                sales_filter: salesFilter
            })
        },
        enabled: !!user?.id
    })

    const items = response?.data || []
    const pagination = response?.pagination || {
        current_page: 1,
        total_pages: 1,
        total_items: 0,
        items_per_page: 100
    }

    // Calculate days without sale
    const processedItems = items.map((item: any) => {
        let days = 0
        if (item.last_sale_date) {
            const last = new Date(item.last_sale_date).getTime()
            const now = new Date().getTime()
            days = Math.floor((now - last) / (1000 * 3600 * 24))
        } else if (item.sold_quantity > 0) {
            // Had sales but no last_sale_date, estimate high
            days = 999
        } else if (item.date_created) {
            const created = new Date(item.date_created).getTime()
            const now = new Date().getTime()
            days = Math.floor((now - created) / (1000 * 3600 * 24))
        }
        return { ...item, days_without_sale: days } as Item
    })

    // Handlers
    const handleBulkPause = async () => {
        if (selectedItems.size === 0) {
            setAlertMessage({ type: 'error', text: 'Por favor, selecione pelo menos um anúncio.' })
            return
        }

        if (!confirm(`Pausar ${selectedItems.size} anúncio(s) no Mercado Livre?`)) return

        setIsBulkPausing(true)
        try {
            const result = await api.bulkPause(Array.from(selectedItems))
            setAlertMessage({
                type: 'success',
                text: `Pausados: ${result.data.success}, Falhas: ${result.data.failed}`
            })
            setSelectedItems(new Set())
            setTimeout(() => refetch(), 2000)
        } catch (error: any) {
            setAlertMessage({ type: 'error', text: error.message })
        } finally {
            setIsBulkPausing(false)
        }
    }

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
        if (selectedItems.size === processedItems.length && processedItems.length > 0) {
            setSelectedItems(new Set())
        } else {
            setSelectedItems(new Set(processedItems.map((i: Item) => i.ml_id)))
        }
    }

    // Sale Tag Helper
    const getSaleTag = (item: Item) => {
        const days = item.days_without_sale || 0

        if (item.sold_quantity === 0) {
            return { text: 'Nunca Vendeu', class: 'bg-gray-700 text-white' }
        }
        if (!item.last_sale_date) {
            return { text: '', class: '' }
        }
        if (days === 0) {
            return { text: 'Vendeu Hoje', class: 'bg-green-100 text-green-800' }
        }
        if (days <= 60) {
            return { text: `${days} dias s/ venda`, class: 'bg-yellow-100 text-yellow-800' }
        }
        return { text: `${days} dias s/ venda`, class: 'bg-red-100 text-red-800' }
    }

    // Auto-hide alerts
    if (alertMessage) {
        setTimeout(() => setAlertMessage(null), 6000)
    }

    if (isLoading) return <div className="p-8 text-center">Carregando inventário...</div>
    if (error) return (
        <div className="p-8 text-center text-red-500">
            <p className="font-bold">Erro ao carregar dados</p>
            <p className="text-sm mt-2">{(error as any).message}</p>
        </div>
    )

    return (
        <div className="space-y-6 pb-20">
            <SyncStatus />

            {/* Alert Messages */}
            {alertMessage && (
                <div className={cn(
                    "p-4 rounded-lg text-sm font-medium",
                    alertMessage.type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                )}>
                    {alertMessage.text}
                </div>
            )}

            {/* Main Card */}
            <div className="bg-white shadow rounded-lg p-6">
                {/* Header */}
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">📊 Seus Anúncios ({pagination.total_items})</h2>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleBulkPause}
                            disabled={selectedItems.size === 0 || isBulkPausing}
                            className="px-3 py-1.5 text-sm font-medium text-white bg-orange-500 rounded-md hover:bg-orange-600 disabled:opacity-50 flex items-center gap-1"
                        >
                            <Pause className="w-4 h-4" />
                            {isBulkPausing ? 'Pausando...' : 'Pausar Selecionados'}
                        </button>
                        <button
                            onClick={() => api.exportCSV()}
                            className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-1"
                        >
                            <Download className="w-4 h-4" />
                            Baixar CSV
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 pb-4 border-b">
                    {/* Status Filter */}
                    <div>
                        <label className="text-sm font-medium text-gray-600 mb-2 block">Filtrar por Status:</label>
                        <div className="flex flex-wrap gap-2">
                            {[
                                { key: 'all', label: 'Todos' },
                                { key: 'active', label: 'Ativos' },
                                { key: 'paused', label: 'Pausados' },
                                { key: 'no_stock', label: 'Sem Estoque' },
                                { key: 'closed', label: 'Finalizados' }
                            ].map(({ key, label }) => (
                                <button
                                    key={key}
                                    onClick={() => {
                                        setStatusFilter(key as any)
                                        setCurrentPage(1)
                                    }}
                                    className={cn(
                                        "px-3 py-1 text-sm font-medium rounded-full border transition-colors",
                                        statusFilter === key
                                            ? 'bg-blue-600 text-white border-blue-700'
                                            : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Sales Filter */}
                    <div>
                        <label htmlFor="sales-filter" className="text-sm font-medium text-gray-600 mb-2 block">
                            Filtrar por Tempo sem Venda:
                        </label>
                        <select
                            id="sales-filter"
                            value={salesFilter}
                            onChange={(e) => {
                                setSalesFilter(e.target.value as any)
                                setCurrentPage(1)
                            }}
                            className="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="all">Qualquer período</option>
                            <option value="never_sold">Nunca Vendeu</option>
                            <option value="over_30">Sem venda há +30 dias</option>
                            <option value="over_60">Sem venda há +60 dias</option>
                            <option value="over_90">Sem venda há +90 dias</option>
                        </select>
                    </div>
                </div>

                {/* Pagination Controls */}
                <div className="flex justify-between items-center mb-4">
                    <div className="flex items-center gap-2 text-sm">
                        <span>Exibir</span>
                        <select
                            value={itemsPerPage}
                            onChange={(e) => {
                                setItemsPerPage(Number(e.target.value))
                                setCurrentPage(1)
                            }}
                            className="p-1 border border-gray-300 rounded-md"
                        >
                            <option value={100}>100</option>
                            <option value={200}>200</option>
                            <option value={500}>500</option>
                        </select>
                        <span>por página</span>
                    </div>

                    {pagination.total_pages > 1 && (
                        <div className="flex items-center gap-1 text-sm">
                            <button
                                onClick={() => setCurrentPage(1)}
                                disabled={currentPage === 1}
                                className="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                «
                            </button>
                            <button
                                onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                                disabled={currentPage === 1}
                                className="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ‹
                            </button>
                            <span className="px-3 py-1">
                                Página {currentPage} de {pagination.total_pages}
                            </span>
                            <button
                                onClick={() => setCurrentPage(Math.min(pagination.total_pages, currentPage + 1))}
                                disabled={currentPage >= pagination.total_pages}
                                className="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ›
                            </button>
                            <button
                                onClick={() => setCurrentPage(pagination.total_pages)}
                                disabled={currentPage >= pagination.total_pages}
                                className="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                »
                            </button>
                        </div>
                    )}
                </div>

                {/* Table */}
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 w-12">
                                    <input
                                        type="checkbox"
                                        checked={selectedItems.size === processedItems.length && processedItems.length > 0}
                                        onChange={toggleSelectAll}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Anúncio</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Frete (Médio)</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Peso</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Visitas</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Vendas</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Última Venda</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Tag de Venda</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {processedItems.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-10 text-gray-500">
                                        Nenhum anúncio encontrado com os filtros atuais.
                                    </td>
                                </tr>
                            ) : (
                                processedItems.map((item: Item) => {
                                    const tag = getSaleTag(item)
                                    return (
                                        <tr key={item.ml_id}>
                                            <td className="px-4 py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedItems.has(item.ml_id)}
                                                    onChange={() => toggleSelection(item.ml_id)}
                                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="text-sm font-medium truncate max-w-xs" title={item.title}>
                                                    {item.title}
                                                </div>
                                                <div className="text-xs text-gray-500">{item.ml_id}</div>
                                            </td>
                                            <td className="px-4 py-2">
                                                <span className={cn(
                                                    "px-2 py-1 text-xs font-semibold rounded-full",
                                                    item.status === 'active' && 'bg-green-100 text-green-800',
                                                    item.status === 'paused' && 'bg-yellow-100 text-yellow-800',
                                                    item.status === 'closed' && 'bg-red-100 text-red-800'
                                                )}>
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                {item.shipping_cost_nacional
                                                    ? `R$ ${Number(item.shipping_cost_nacional).toFixed(2)}`
                                                    : <span className="text-gray-400">-</span>
                                                }
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                <div className="flex items-center gap-1">
                                                    {item.weight_status === 'good' && <span title="Peso Ideal">🟢</span>}
                                                    {item.weight_status === 'acceptable' && <span title="Aceitável">🟡</span>}
                                                    {item.weight_status === 'wrong' && <span title="Peso Incorreto/Alto">🔴</span>}
                                                    <span className="text-xs text-gray-600">
                                                        {item.billable_weight ? `${item.billable_weight}g` : '-'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                {item.total_visits?.toLocaleString() || '0'}
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                {item.sold_quantity.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                {item.last_sale_date
                                                    ? new Date(item.last_sale_date).toLocaleDateString('pt-BR')
                                                    : '-'
                                                }
                                            </td>
                                            <td className="px-4 py-2">
                                                {tag.text && (
                                                    <span className={cn(
                                                        "px-2 py-0.5 text-xs font-medium rounded-full",
                                                        tag.class
                                                    )}>
                                                        {tag.text}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    )
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    )
}
