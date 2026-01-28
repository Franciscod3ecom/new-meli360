
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import { Download } from 'lucide-react'
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
    const [currentPage, setCurrentPage] = useState(1)
    const [itemsPerPage, setItemsPerPage] = useState(100)
    const [alertMessage, setAlertMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

    // Query Data
    const { data: response, isLoading, error } = useQuery({
        queryKey: ['items', user?.id, currentPage, itemsPerPage, statusFilter],
        queryFn: async () => {
            if (!user?.id) return null
            return await api.getItems({
                page: currentPage,
                limit: itemsPerPage,
                status_filter: statusFilter,
                sales_filter: 'all'
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

    // Mappers
    const getShippingModeLabel = (mode: string) => {
        const map: Record<string, string> = {
            'me1': 'Envio Próprio',
            'me2': 'Mercado Envios',
            'custom': 'Personalizado',
            'not_specified': 'Não especificado'
        }
        return map[mode] || mode || 'Não definido'
    }

    const getLogisticTypeLabel = (type: string) => {
        const map: Record<string, string> = {
            'fulfillment': 'Full',
            'cross_docking': 'Coleta',
            'self_service': 'Flex',
            'drop_off': 'Agência',
            'xd_drop_off': 'Agência (Coleta)'
        }
        return map[type] || type || 'Padrão'
    }

    // Freight Quality Visual
    const getFreightQuality = (item: Item) => {
        if (item.free_shipping) return { label: 'Grátis', color: 'bg-green-100 text-green-800', icon: '🌟' }
        
        const cost = item.shipping_cost_nacional || 0
        if (cost === 0) return { label: 'A Calcular', color: 'bg-gray-100 text-gray-800', icon: '❓' }
        if (cost < 25) return { label: 'Bom', color: 'bg-green-50 text-green-700', icon: '🙂' }
        if (cost < 50) return { label: 'Médio', color: 'bg-yellow-50 text-yellow-700', icon: '😐' }
        return { label: 'Ruim', color: 'bg-red-50 text-red-700', icon: '😟' }
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
                    <h2 className="text-lg font-semibold">🚚 Gestão de Fretes ({pagination.total_items})</h2>
                    <div className="flex items-center gap-2">
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
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Img</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Anúncio</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Frete (Médio)</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Qualidade</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Peso</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Modo</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Logística</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {processedItems.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-10 text-gray-500">
                                        Nenhum anúncio encontrado com os filtros atuais.
                                    </td>
                                </tr>
                            ) : (
                                processedItems.map((item: Item) => {
                                    const quality = getFreightQuality(item)
                                    return (
                                        <tr key={item.ml_id}>
                                            <td className="px-4 py-2">
                                                <img
                                                    src={item.secure_thumbnail || item.thumbnail}
                                                    alt=""
                                                    className="w-12 h-12 object-contain rounded border border-gray-100"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="text-sm font-medium truncate max-w-xs" title={item.title}>
                                                    {item.title}
                                                </div>
                                                <div className="text-xs text-gray-500">{item.ml_id}</div>
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                {item.shipping_cost_nacional
                                                    ? `R$ ${Number(item.shipping_cost_nacional).toFixed(2)}`
                                                    : <span className="text-gray-400">-</span>
                                                }
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                <span className={cn("px-2 py-0.5 rounded text-xs font-medium flex items-center gap-1 w-fit", quality.color)}>
                                                    <span>{quality.icon}</span>
                                                    {quality.label}
                                                </span>
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
                                                <span className="px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-mono">
                                                    {getShippingModeLabel(item.shipping_mode)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-sm">
                                                <span className="px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-medium">
                                                    {getLogisticTypeLabel(item.logistic_type)}
                                                </span>
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
