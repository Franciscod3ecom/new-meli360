
import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import { Download, Pause, RefreshCw, LogOut, Search, ChevronRight, ChevronDown, Package, Activity, Info, AlertTriangle } from 'lucide-react'
import { cn } from '../../../lib/utils'
import { toast } from 'sonner'

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
    freight_brasilia?: number
    freight_sao_paulo?: number
    freight_salvador?: number
    freight_manaus?: number
    freight_porto_alegre?: number
    me2_restrictions?: string
    category_id?: string
    category_dimensions?: string
    category_logistics?: string
    category_restricted?: boolean
    category_last_modified?: string
    avg_category_freight?: number
    catalog_listing?: boolean
}

export default function Dashboard() {
    const { user, logout } = useAuth()

    // State
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'paused' | 'no_stock' | 'closed'>('all')
    const [salesFilter, setSalesFilter] = useState<'all' | 'never_sold' | 'over_30' | 'over_60' | 'over_90'>('all')
    const [searchTerm, setSearchTerm] = useState('')
    const [currentPage, setCurrentPage] = useState(1)
    const [itemsPerPage, setItemsPerPage] = useState(100)
    const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set())
    const [expandedItems, setExpandedItems] = useState<Set<string>>(new Set())
    const [isBulkPausing, setIsBulkPausing] = useState(false)
    const [alertMessage, setAlertMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

    // Query Data
    const { data: response, isLoading, error, refetch } = useQuery({
        queryKey: ['items', user?.id, currentPage, itemsPerPage, statusFilter, salesFilter, searchTerm],
        queryFn: async () => {
            if (!user?.id) return null
            return await api.getItems({
                page: currentPage,
                limit: itemsPerPage,
                status_filter: statusFilter,
                sales_filter: salesFilter,
                search: searchTerm
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

    const toggleExpand = (id: string) => {
        const newExpanded = new Set(expandedItems)
        if (newExpanded.has(id)) {
            newExpanded.delete(id)
        } else {
            newExpanded.add(id)
        }
        setExpandedItems(newExpanded)
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

    // Translation Helper
    const getStatusLabel = (status: string) => {
        const map: Record<string, string> = {
            'active': 'Ativo',
            'paused': 'Pausado',
            'closed': 'Finalizado',
            'no_stock': 'Sem Estoque'
        }
        return map[status] || status
    }

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


    const formatCurrency = (val?: number) => val ? `R$ ${Number(val).toFixed(2)}` : '-'

    const parseJSON = (str?: string) => {
        if (!str) return null
        try { return JSON.parse(str) } catch (e) { return null }
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
                    <h2 className="text-lg font-semibold">📊 Seus Anúncios ({pagination.total_items})</h2>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={async () => {
                                try {
                                    toast.loading('Sincronizando...', { id: 'sync-toast' })
                                    const result = await api.triggerSync()
                                    toast.success(result.message || 'Sincronização concluída!', { id: 'sync-toast' })
                                    refetch() // Recarregar dados
                                } catch (error: any) {
                                    toast.error(error.message || 'Erro na sincronização', { id: 'sync-toast' })
                                }
                            }}
                            className="px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 flex items-center gap-1"
                        >
                            <RefreshCw className="w-4 h-4" />
                            Sincronizar
                        </button>
                        <button
                            onClick={handleBulkPause}
                            disabled={selectedItems.size === 0 || isBulkPausing}
                            className="px-3 py-1.5 text-sm font-medium text-white bg-orange-500 rounded-md hover:bg-orange-600 disabled:opacity-50 flex items-center gap-1"
                        >
                            <Pause className="w-4 h-4" />
                            {isBulkPausing ? 'Pausando...' : 'Pausar'}
                        </button>
                        <button
                            onClick={() => api.exportCSV()}
                            className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-1"
                        >
                            <Download className="w-4 h-4" />
                            Baixar CSV
                        </button>
                        <button
                            onClick={logout}
                            className="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50 flex items-center gap-1"
                        >
                            <LogOut className="w-4 h-4" />
                            Sair
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-4 pb-4 border-b">
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
                                            ? 'bg-yellow-500 text-white border-yellow-600'
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

                    {/* Search Bar */}
                    <div>
                        <label className="text-sm font-medium text-gray-600 mb-2 block">Buscar por MLB:</label>
                        <div className="relative group">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors">
                                <Search className="w-4 h-4" />
                            </span>
                            <input
                                type="text"
                                placeholder="Ex: MLB4107595224"
                                value={searchTerm}
                                onChange={(e) => {
                                    setSearchTerm(e.target.value.toUpperCase())
                                    setCurrentPage(1)
                                }}
                                className="block w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                            />
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
                                <th className="px-4 py-3 w-12">
                                    <input
                                        type="checkbox"
                                        checked={selectedItems.size === processedItems.length && processedItems.length > 0}
                                        onChange={toggleSelectAll}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
                                <th className="px-4 py-3 w-8"></th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Img</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Anúncio</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Estoque</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Criação</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Visitas</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Vendas</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Última Venda</th>
                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Tag de Venda</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {processedItems.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-center py-10 text-gray-500">
                                        Nenhum anúncio encontrado com os filtros atuais.
                                    </td>
                                </tr>
                            ) : (
                                processedItems.map((item: Item) => {
                                    const tag = getSaleTag(item)
                                    const isExpanded = expandedItems.has(item.ml_id)
                                    const dims = parseJSON(item.category_dimensions)

                                    let weightColor = 'text-gray-400'
                                    if (item.weight_status?.includes('bom')) weightColor = 'text-green-600 bg-green-50'
                                    if (item.weight_status?.includes('aceitável')) weightColor = 'text-yellow-600 bg-yellow-50'
                                    if (item.weight_status?.includes('errado')) weightColor = 'text-red-600 bg-red-50'

                                    return (
                                        <React.Fragment key={item.ml_id}>
                                            <tr className={cn("hover:bg-gray-50 transition-colors", isExpanded && "bg-blue-50/30")}>
                                                <td className="px-4 py-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedItems.has(item.ml_id)}
                                                        onChange={() => toggleSelection(item.ml_id)}
                                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <button
                                                        onClick={() => toggleExpand(item.ml_id)}
                                                        className="p-1 hover:bg-gray-200 rounded-md transition-colors text-gray-400"
                                                        title="Ver detalhes de frete"
                                                    >
                                                        {isExpanded ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
                                                    </button>
                                                </td>
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
                                                    <div className="flex items-center gap-1.5 mt-0.5">
                                                        <span className="text-[10px] text-gray-500 font-mono">{item.ml_id}</span>
                                                        {item.catalog_listing && (
                                                            <span className="bg-blue-600 text-white text-[8px] font-bold px-1 rounded shadow-sm">CAT</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2">
                                                    <span className={cn(
                                                        "px-2 py-1 text-xs font-semibold rounded-full",
                                                        item.status === 'active' && 'bg-green-100 text-green-800',
                                                        item.status === 'paused' && 'bg-yellow-100 text-yellow-800',
                                                        item.status === 'closed' && 'bg-red-100 text-red-800'
                                                    )}>
                                                        {getStatusLabel(item.status)}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2 text-sm text-center font-mono">
                                                    {item.available_quantity.toLocaleString()}
                                                </td>
                                                <td className="px-4 py-2 text-sm">
                                                    {item.date_created ? new Date(item.date_created).toLocaleDateString('pt-BR') : '-'}
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
                                            {isExpanded && (
                                                <tr className="bg-gray-50/50">
                                                    <td colSpan={11} className="px-6 py-4 border-l-4 border-blue-500">
                                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                                            {/* Col 1: Dimensions & Category */}
                                                            <div>
                                                                <h4 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                                                    <Package className="w-3 h-3" /> Categoria e Dimensões
                                                                </h4>
                                                                <div className="text-sm font-semibold text-gray-700">{item.category_name || 'N/A'}</div>
                                                                {dims && typeof dims === 'object' && (dims.height || dims.width || dims.length || dims.weight) ? (
                                                                    <div className="text-[11px] text-gray-500 mt-2 space-y-1">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span className="text-blue-500">📏</span>
                                                                            <span>{dims.height}x{dims.width}x{dims.length}cm</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span className="text-orange-500">⚖️</span>
                                                                            <span>{dims.weight}g</span>
                                                                        </div>
                                                                    </div>
                                                                ) : (
                                                                    <div className="mt-1 text-[10px] text-gray-400 italic">Dimensões não vinculadas</div>
                                                                )}
                                                            </div>

                                                            {/* Col 2: Quality & Rules */}
                                                            <div>
                                                                <h4 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                                                    <Activity className="w-3 h-3" /> Qualidade e Regras
                                                                </h4>
                                                                <div className="space-y-3">
                                                                    <div>
                                                                        <div className="flex justify-between items-center mb-1">
                                                                            <span className="text-[10px] text-gray-500 font-medium uppercase">Saúde do Anúncio</span>
                                                                            <span className="text-xs font-bold text-gray-700">{item.health ? Math.round(item.health * 100) : 0}%</span>
                                                                        </div>
                                                                        <div className="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                                                            <div
                                                                                className={cn(
                                                                                    "h-full transition-all",
                                                                                    (item.health || 0) > 0.8 ? "bg-green-500" : (item.health || 0) > 0.5 ? "bg-yellow-500" : "bg-red-500"
                                                                                )}
                                                                                style={{ width: `${(item.health || 0) * 100}%` }}
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                    <div className="pt-2 border-t border-gray-50">
                                                                        <div className="text-[9px] text-gray-400 uppercase font-bold leading-tight">Última Alteração de Regras</div>
                                                                        <div className="text-xs font-semibold text-gray-700 mt-0.5">
                                                                            {item.category_last_modified ? new Date(item.category_last_modified).toLocaleDateString('pt-BR') : 'Não disponível'}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Col 3: Weight Status */}
                                                            <div>
                                                                <h4 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                                                    <Activity className="w-3 h-3" /> Status do Peso
                                                                </h4>
                                                                <div className={cn("p-2 rounded-lg border text-[11px] font-medium", weightColor)}>
                                                                    {item.weight_status?.replace(/[🟢🔴]/g, '') || 'N/A'}
                                                                    <div className="mt-1 text-[10px] opacity-70 border-t border-current/10 pt-1">
                                                                        Peso faturável: {item.billable_weight ? Number(item.billable_weight).toFixed(0) : 0}g
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Col 4: Regional Costs */}
                                                            <div>
                                                                <h4 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                                                    <Info className="w-3 h-3" /> Fretes Regionais
                                                                </h4>
                                                                <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-[10px]">
                                                                    <div className="flex justify-between border-b border-gray-100 pb-0.5">
                                                                        <span className="text-gray-400">Brasília</span>
                                                                        <span className="font-medium text-gray-600">{formatCurrency(item.freight_brasilia)}</span>
                                                                    </div>
                                                                    <div className="flex justify-between border-b border-gray-100 pb-0.5">
                                                                        <span className="text-gray-400">São Paulo</span>
                                                                        <span className="font-medium text-gray-600">{formatCurrency(item.freight_sao_paulo)}</span>
                                                                    </div>
                                                                    <div className="flex justify-between border-b border-gray-100 pb-0.5">
                                                                        <span className="text-gray-400">Salvador</span>
                                                                        <span className="font-medium text-gray-600">{formatCurrency(item.freight_salvador)}</span>
                                                                    </div>
                                                                    <div className="flex justify-between border-b border-gray-100 pb-0.5">
                                                                        <span className="text-gray-400">Manaus</span>
                                                                        <span className="font-medium text-gray-600">{formatCurrency(item.freight_manaus)}</span>
                                                                    </div>
                                                                    <div className="flex justify-between border-b border-gray-100 pb-0.5">
                                                                        <span className="text-gray-400">Porto Alegre</span>
                                                                        <span className="font-medium text-gray-600">{formatCurrency(item.freight_porto_alegre)}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* Bottom Tags */}
                                                        <div className="mt-4 flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                                                            <span className="text-[10px] font-bold text-gray-400 uppercase mr-1">Tags:</span>
                                                            <span className="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded border border-blue-100 uppercase">
                                                                {getLogisticTypeLabel(item.logistic_type)}
                                                            </span>
                                                            <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded border border-gray-200 uppercase">
                                                                {getShippingModeLabel(item.shipping_mode)}
                                                            </span>
                                                            {item.free_shipping && (
                                                                <span className="px-2 py-0.5 bg-green-500 text-white text-[10px] font-bold rounded shadow-sm uppercase">FRETE GRÁTIS</span>
                                                            )}
                                                            {item.category_restricted && (
                                                                <span className="px-2 py-0.5 bg-red-600 text-white text-[10px] font-bold rounded shadow-sm uppercase flex items-center gap-1">
                                                                    <AlertTriangle className="w-3 h-3" /> RESTRITO
                                                                </span>
                                                            )}
                                                            {item.me2_restrictions && parseJSON(item.me2_restrictions)?.map((rest: string, i: number) => (
                                                                <span key={i} className="px-2 py-0.5 bg-red-50 text-red-600 text-[10px] font-bold rounded border border-red-100 uppercase">
                                                                    🚫 {rest}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
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
