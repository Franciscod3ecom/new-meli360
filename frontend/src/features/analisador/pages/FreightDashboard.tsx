
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import { Download, RefreshCw, Search } from 'lucide-react'
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
    // New Fields
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
    const { user } = useAuth()

    // State
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'paused' | 'no_stock' | 'closed'>('all')
    const [searchTerm, setSearchTerm] = useState('')
    const [currentPage, setCurrentPage] = useState(1)
    const [itemsPerPage, setItemsPerPage] = useState(100)
    const [alertMessage, setAlertMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
    const [isSyncing, setIsSyncing] = useState(false)

    // Query Data
    const { data: response, isLoading, error, refetch } = useQuery({
        queryKey: ['items', user?.id, currentPage, itemsPerPage, statusFilter, searchTerm],
        queryFn: async () => {
            if (!user?.id) return null
            return await api.getItems({
                page: currentPage,
                limit: itemsPerPage,
                status_filter: statusFilter,
                search: searchTerm,
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

    const formatCurrency = (val?: number) => val ? `R$ ${Number(val).toFixed(2)}` : '-'

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

    const runSync = async () => {
        if (isSyncing) return
        setIsSyncing(true)

        try {
            toast.loading('Sincronizando dados de frete...', { id: 'sync-toast' })
            const result = await api.triggerSync()
            toast.success(result.message || 'Sincronização concluída!', { id: 'sync-toast' })
            refetch()
        } catch (error: any) {
            toast.error(error.message || 'Erro na sincronização', { id: 'sync-toast' })
        } finally {
            setIsSyncing(false)
        }
    }

    const parseJSON = (str?: string) => {
        if (!str) return null
        try {
            return JSON.parse(str)
        } catch (e) {
            return null
        }
    }

    // Auto-hide alerts
    if (alertMessage) {
        setTimeout(() => setAlertMessage(null), 6000)
    }

    if (isLoading) return <div className="p-8 text-center text-gray-600">Carregando inventário de fretes...</div>
    if (error) return (
        <div className="p-8 text-center text-red-500">
            <p className="font-bold">Erro ao carregar dados</p>
            <p className="text-sm mt-2">{(error as any).message}</p>
        </div>
    )

    return (
        <div className="space-y-6 pb-20 px-4">
            {/* Alert Messages */}
            {alertMessage && (
                <div className={cn(
                    "p-4 rounded-lg text-sm font-medium animate-in fade-in slide-in-from-top-4 duration-300",
                    alertMessage.type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'
                )}>
                    {alertMessage.text}
                </div>
            )}

            {/* Main Card */}
            <div className="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
                {/* Header */}
                <div className="p-6 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-gray-800 flex items-center gap-2">
                            🚚 Gestão de Fretes
                            <span className="text-sm font-normal text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full">
                                {pagination.total_items} produtos
                            </span>
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">Análise detalhada de custos logísticos e correções de peso.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={runSync}
                            disabled={isSyncing}
                            className="px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 flex items-center gap-1 active:scale-95 transition-all disabled:opacity-50"
                        >
                            <RefreshCw className={cn("w-4 h-4", isSyncing && "animate-spin")} />
                            {isSyncing ? 'Sincronizando...' : 'Sincronizar'}
                        </button>
                        <button
                            onClick={() => api.exportCSV()}
                            className="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all flex items-center gap-2 shadow-sm"
                        >
                            <Download className="w-4 h-4" />
                            Exportar Relatório
                        </button>
                    </div>
                </div>

                {/* Filters and Search Area */}
                <div className="p-6 border-b border-gray-100 space-y-6">
                    <div className="flex flex-col xl:flex-row xl:items-end justify-between gap-6">
                        {/* Status Filters */}
                        <div className="flex-1 space-y-3">
                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider block">Filtrar por Status:</label>
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
                                            "px-4 py-1.5 text-sm font-semibold rounded-full border transition-all",
                                            statusFilter === key
                                                ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-100'
                                                : 'bg-white text-gray-600 hover:bg-gray-50 border-gray-200'
                                        )}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Search Bar */}
                        <div className="w-full xl:w-80 space-y-3">
                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider block">Buscar por MLB:</label>
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
                                    className="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all placeholder:text-gray-400 font-medium"
                                />
                                {searchTerm && (
                                    <button
                                        onClick={() => { setSearchTerm(''); setCurrentPage(1); }}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5"
                                    >
                                        ✕
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Pagination Select and Controls */}
                        <div className="flex flex-col sm:flex-row items-center gap-4">
                            <div className="flex items-center gap-2 text-sm w-full sm:w-auto">
                                <span className="text-gray-500 font-medium whitespace-nowrap">Exibir:</span>
                                <select
                                    value={itemsPerPage}
                                    onChange={(e) => {
                                        setItemsPerPage(Number(e.target.value))
                                        setCurrentPage(1)
                                    }}
                                    className="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none bg-white cursor-pointer"
                                >
                                    <option value={100}>100 por página</option>
                                    <option value={200}>200 por página</option>
                                    <option value={500}>500 por página</option>
                                </select>
                            </div>

                            {pagination.total_pages > 1 && (
                                <div className="flex items-center gap-1 bg-gray-100/50 p-1 rounded-xl border border-gray-100">
                                    <button
                                        onClick={() => setCurrentPage(1)}
                                        disabled={currentPage === 1}
                                        className="p-1.5 px-3 text-xs font-bold bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-all active:scale-95"
                                        title="Primeira página"
                                    >
                                        «
                                    </button>
                                    <button
                                        onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                        disabled={currentPage === 1}
                                        className="p-1.5 px-3 text-xs font-bold bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-all active:scale-95"
                                        title="Anterior"
                                    >
                                        ‹
                                    </button>
                                    <div className="px-3 min-w-[120px] text-center">
                                        <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Página</div>
                                        <div className="text-sm font-bold text-gray-700">{currentPage} <span className="text-gray-300 px-1">/</span> {pagination.total_pages}</div>
                                    </div>
                                    <button
                                        onClick={() => setCurrentPage(prev => Math.min(pagination.total_pages, prev + 1))}
                                        disabled={currentPage === pagination.total_pages}
                                        className="p-1.5 px-3 text-xs font-bold bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-all active:scale-95"
                                        title="Próxima"
                                    >
                                        ›
                                    </button>
                                    <button
                                        onClick={() => setCurrentPage(pagination.total_pages)}
                                        disabled={currentPage === pagination.total_pages}
                                        className="p-1.5 px-3 text-xs font-bold bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-all active:scale-95"
                                        title="Última página"
                                    >
                                        »
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 table-fixed md:table-auto">
                        <thead className="bg-gray-50/50">
                            <tr>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[140px]">Produto</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[160px]">Categoria e Dimensões</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[170px]">Frete (Atual vs Catálogo)</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[160px]">Status do Peso</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[200px]">Fretes Regionais (R$)</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[120px]">Logística</th>
                                <th className="px-6 py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest w-[160px]">Tags</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-100">
                            {processedItems.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-20 text-gray-400">
                                        <div className="flex flex-col items-center gap-2">
                                            <span className="text-4xl text-gray-200">📦</span>
                                            <p className="font-medium">Nenhum anúncio encontrado.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                processedItems.map((item: Item) => {
                                    const quality = getFreightQuality(item)
                                    const dims = parseJSON(item.category_dimensions)

                                    // Weight Logic Color
                                    let weightColor = 'text-gray-400'
                                    if (item.weight_status?.includes('bom')) weightColor = 'text-green-600 bg-green-50'
                                    if (item.weight_status?.includes('aceitável')) weightColor = 'text-yellow-600 bg-yellow-50'
                                    if (item.weight_status?.includes('errado')) weightColor = 'text-red-600 bg-red-50'

                                    return (
                                        <tr key={item.ml_id} className="hover:bg-gray-50 transition-colors group">
                                            <td className="px-6 py-4 align-top">
                                                <div className="flex items-center gap-3">
                                                    <div className="relative flex-shrink-0">
                                                        <img
                                                            src={item.secure_thumbnail || item.thumbnail}
                                                            alt=""
                                                            className="w-14 h-14 object-cover rounded-lg border border-gray-200 bg-white"
                                                        />
                                                        {item.catalog_listing && (
                                                            <span className="absolute -top-1 -right-1 bg-blue-600 text-white text-[8px] font-bold px-1 rounded shadow-sm">CAT</span>
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="text-sm font-medium text-gray-800 truncate" title={item.title}>
                                                            {item.title}
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-1.5 mt-1">
                                                            <span className="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded uppercase tracking-wider">{item.ml_id}</span>
                                                            <span className={cn(
                                                                "text-[10px] font-medium px-1.5 py-0.5 rounded uppercase tracking-wider",
                                                                item.status === 'active' ? 'text-green-600 bg-green-50' : 'text-yellow-600 bg-yellow-50'
                                                            )}>
                                                                {getStatusLabel(item.status)}
                                                            </span>
                                                            {item.health !== undefined && (
                                                                <div className="flex items-center gap-1 ml-1" title={`Saúde do anúncio: ${Math.round(item.health * 100)}%`}>
                                                                    <div className="w-12 h-1.5 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                                                        <div
                                                                            className={cn(
                                                                                "h-full transition-all",
                                                                                item.health > 0.8 ? "bg-green-500" : item.health > 0.5 ? "bg-yellow-500" : "bg-red-500"
                                                                            )}
                                                                            style={{ width: `${item.health * 100}%` }}
                                                                        />
                                                                    </div>
                                                                    <span className="text-[9px] font-bold text-gray-500">{Math.round(item.health * 100)}%</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className="text-sm font-semibold text-gray-700">{item.category_name || 'N/A'}</div>
                                                {item.category_last_modified && (
                                                    <div className="text-[8px] text-gray-400 uppercase mt-0.5">Última Atualização de Regras: {new Date(item.category_last_modified).toLocaleDateString('pt-BR')}</div>
                                                )}

                                                {dims && typeof dims === 'object' && (dims.height || dims.width || dims.length || dims.weight) ? (
                                                    <div className="text-[11px] text-gray-500 mt-2 font-medium bg-gray-50 p-2 rounded-lg border border-gray-100 space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="text-blue-500 text-[10px]">📏</span>
                                                            <span>{dims.height}x{dims.width}x{dims.length}cm</span>
                                                        </div>
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="text-orange-500 text-[10px]">⚖️</span>
                                                            <span>{dims.weight}g</span>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="mt-2 text-[10px] text-gray-400 italic bg-gray-50 p-2 rounded-lg border border-dashed border-gray-200">
                                                        Dimensões não vinculadas
                                                    </div>
                                                )}

                                                {item.category_logistics && (
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        {parseJSON(item.category_logistics)?.map((log: any, i: number) => (
                                                            <span key={i} className="text-[8px] px-1 bg-gray-100 text-gray-500 rounded border border-gray-200 uppercase" title={log.logistic_type}>
                                                                {getLogisticTypeLabel(log.logistic_type)}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className="flex flex-col h-full justify-between gap-3">
                                                    <div className="space-y-2 pb-2 border-b border-gray-50">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-[10px] font-medium text-gray-400 uppercase shrink-0">Item:</span>
                                                            <span className="text-sm font-medium text-gray-700 font-mono">{formatCurrency(item.shipping_cost_nacional)}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-[10px] font-semibold text-gray-400 uppercase shrink-0">Médio:</span>
                                                            <span className="text-sm font-medium text-gray-700 font-mono">{formatCurrency(item.avg_category_freight)}</span>
                                                        </div>
                                                    </div>
                                                    <span className={cn("inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter shadow-sm border", quality.color)}>
                                                        <span>{quality.icon}</span> {quality.label}
                                                    </span>
                                                    {item.price >= 79 && !item.free_shipping && (
                                                        <div className="mt-1 text-[8px] font-bold text-red-500 uppercase flex items-center gap-1 bg-red-50 p-1 rounded border border-red-100">
                                                            ⚠️ Preço ≥ R$ 79 sem Frete Grátis
                                                        </div>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className={cn("px-3 py-2 rounded-xl border border-transparent transition-all h-full flex flex-col justify-center", weightColor)}>
                                                    <div className="text-sm font-semibold flex items-center gap-1.5">
                                                        {item.weight_status?.replace(/[🟢🔴]/g, '') || 'N/A'}
                                                    </div>
                                                    <div className="text-[12px] font-medium mt-1 border-t border-current/10 pt-1">
                                                        Peso faturável para envio: {item.billable_weight || 0}g
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className="flex flex-col gap-1 text-[10.5px] font-medium text-gray-600 bg-gray-50/50 p-2 rounded-xl border border-gray-100/50">
                                                    <div className="flex justify-between items-center bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm gap-4">
                                                        <span className="text-[9px] text-gray-500 uppercase tracking-widest">Brasília</span>
                                                        <span className="text-gray-800 font-mono">{formatCurrency(item.freight_brasilia)}</span>
                                                    </div>
                                                    <div className="flex justify-between items-center bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm gap-4">
                                                        <span className="text-[9px] text-gray-500 uppercase tracking-widest">São Paulo</span>
                                                        <span className="text-gray-800 font-mono">{formatCurrency(item.freight_sao_paulo)}</span>
                                                    </div>
                                                    <div className="flex justify-between items-center bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm gap-4">
                                                        <span className="text-[9px] text-gray-500 uppercase tracking-widest">Salvador</span>
                                                        <span className="text-gray-800 font-mono">{formatCurrency(item.freight_salvador)}</span>
                                                    </div>
                                                    <div className="flex justify-between items-center bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm gap-4">
                                                        <span className="text-[9px] text-gray-500 uppercase tracking-widest">Manaus</span>
                                                        <span className="text-gray-800 font-mono">{formatCurrency(item.freight_manaus)}</span>
                                                    </div>
                                                    <div className="flex justify-between items-center bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm gap-4">
                                                        <span className="text-[9px] text-gray-500 uppercase tracking-widest">Porto Alegre</span>
                                                        <span className="text-gray-800 font-mono">{formatCurrency(item.freight_porto_alegre)}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className="flex flex-col gap-2">
                                                    <div className="flex flex-col items-center">
                                                        <span className="text-[9px] font-semibold text-gray-400 uppercase mb-1">Tipo</span>
                                                        <span className="w-full px-2 py-1 rounded-lg bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-100 uppercase tracking-tighter text-center">
                                                            {getLogisticTypeLabel(item.logistic_type)}
                                                        </span>
                                                    </div>
                                                    <div className="flex flex-col items-center">
                                                        <span className="text-[9px] font-semibold text-gray-400 uppercase mb-1 border-t border-gray-100 w-full pt-1 text-center">Modo</span>
                                                        <span className="w-full px-2 py-1 rounded-lg bg-gray-50 text-gray-500 text-[9px] font-semibold border border-gray-200 uppercase tracking-tighter text-center">
                                                            {getShippingModeLabel(item.shipping_mode)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4 align-top">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {item.me2_restrictions && parseJSON(item.me2_restrictions)?.map((rest: string, i: number) => (
                                                        <span key={i} className="px-2 py-0.5 bg-red-50 text-red-600 text-[9px] font-bold rounded-md border border-red-100 uppercase tracking-tighter">
                                                            🚫 {rest}
                                                        </span>
                                                    ))}
                                                    {item.category_restricted && (
                                                        <span className="px-2 py-0.5 bg-red-600 text-white text-[9px] font-bold rounded-md shadow-sm uppercase tracking-tighter flex items-center gap-1">
                                                            RESTRITO
                                                        </span>
                                                    )}
                                                    {item.free_shipping && (
                                                        <span className="px-2 py-0.5 bg-green-500 text-white text-[9px] font-bold rounded-md shadow-sm uppercase tracking-tighter flex items-center gap-1">
                                                            GRÁTIS
                                                        </span>
                                                    )}
                                                    {!item.me2_restrictions && !item.category_restricted && !item.free_shipping && (
                                                        <span className="text-[10px] text-gray-400 italic bg-gray-50 px-2 py-1 rounded">Sem restrições</span>
                                                    )}
                                                </div>
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
