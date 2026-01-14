
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { supabase } from '../../../lib/supabase'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import { SyncStatus } from '../components/SyncStatus'
import {
    Zap,
    Truck,
    RefreshCw,
    MoreHorizontal,
    Play,
    Pause,
    CheckSquare,
    Square
} from 'lucide-react'
import { cn } from '../../../lib/utils'

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
    days_without_sale?: number
    is_synced?: number
}

export default function Dashboard() {
    const { user } = useAuth() // Get current user
    const [filter, setFilter] = useState<'all' | 'full' | 'stagnant' | 'no_sales'>('all')
    const [isSyncing, setIsSyncing] = useState(false)
    const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set())
    const [isBulkUpdating, setIsBulkUpdating] = useState(false)

    // Query Data - NOW FILTERED BY ACCOUNT
    const { data: items, isLoading, error, refetch } = useQuery({
        queryKey: ['items', user?.id], // Add user.id to cache key
        queryFn: async () => {
            if (!user?.id) return []
            
            const { data, error } = await supabase
                .from('items')
                .select('*')
                .eq('account_id', user.id) // FILTER BY ACCOUNT!
                .order('last_sale_date', { ascending: true, nullsFirst: false })
                .limit(200)

            if (error) throw error

            return data.map((item: any) => {
                let days = 0;
                if (item.last_sale_date) {
                    const last = new Date(item.last_sale_date).getTime();
                    const now = new Date().getTime();
                    days = Math.floor((now - last) / (1000 * 3600 * 24));
                } else if (item.date_created) {
                    const created = new Date(item.date_created).getTime();
                    const now = new Date().getTime();
                    days = Math.floor((now - created) / (1000 * 3600 * 24));
                }
                return { ...item, days_without_sale: days } as Item
            })
        },
        enabled: !!user?.id // Only run query if user is loaded
    })

    // Filter Logic
    const filteredItems = items?.filter(item => {
        if (filter === 'full') return item.logistic_type === 'fulfillment'
        if (filter === 'stagnant') return (item.days_without_sale || 0) > 60
        if (filter === 'no_sales') return item.sold_quantity === 0
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

    const getRowClass = (item: Item) => {
        const days = item.days_without_sale || 0
        if (days > 60) return "bg-red-50 hover:bg-red-100"
        if (days > 30) return "bg-yellow-50 hover:bg-yellow-100"
        return "hover:bg-gray-50"
    }

    if (isLoading) return <div className="p-8 text-center">Carregando inventário...</div>
    if (error) return (
        <div className="p-8 text-center text-red-500">
            <p className="font-bold">Erro ao carregar dados:</p>
            <p className="text-sm font-mono mt-2">{(error as any).message || JSON.stringify(error)}</p>
        </div>
    )

    return (
        <div className="space-y-6 pb-20"> {/* pb-20 for SyncStatus footer */}

            {/* Sync Status Footer */}
            <SyncStatus />

            {/* Controls */}
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center bg-white p-4 rounded-lg shadow-sm border border-gray-100">

                {/* Filters */}
                <div className="flex gap-2">
                    <FilterButton
                        active={filter === 'all'}
                        onClick={() => setFilter('all')}
                        label="Todos"
                    />
                    <FilterButton
                        active={filter === 'full'}
                        onClick={() => setFilter('full')}
                        label="Full"
                        icon={<Zap className="w-3 h-3" />}
                    />
                    <FilterButton
                        active={filter === 'stagnant'}
                        onClick={() => setFilter('stagnant')}
                        label="Parados (+60d)"
                        className="text-red-700 bg-red-50 border-red-200"
                    />
                    <FilterButton
                        active={filter === 'no_sales'}
                        onClick={() => setFilter('no_sales')}
                        label="Sem Vendas"
                    />
                </div>

                {/* Sync Action */}
                <button
                    onClick={handleSync}
                    disabled={isSyncing}
                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-all font-medium text-sm"
                >
                    <RefreshCw className={cn("w-4 h-4", isSyncing && "animate-spin")} />
                    {isSyncing ? 'Sincronizando...' : 'Sincronizar Agora'}
                </button>
            </div>

            {/* Bulk Action Bar */}
            {
                selectedItems.size > 0 && (
                    <div className="fixed bottom-16 left-0 right-0 mx-auto w-max z-50 animate-in fade-in slide-in-from-bottom-4 duration-300">
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
                                <th className="px-4 py-3">Anúncio</th>
                                <th className="px-4 py-3 w-32">Logística</th>
                                <th className="px-4 py-3 w-24 text-center">Vendas</th>
                                <th className="px-4 py-3 w-24 text-center">Dias Parado</th>
                                <th className="px-4 py-3 w-24 text-right">Preço</th>
                                <th className="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {filteredItems?.map(item => (
                                <tr key={item.id} className={cn("transition-colors", getRowClass(item), selectedItems.has(item.ml_id) && "bg-blue-50/50 border-blue-200")}>
                                    <td className="px-4 py-3">
                                        <button onClick={() => toggleSelection(item.ml_id)} className={cn("flex items-center justify-center", selectedItems.has(item.ml_id) ? "text-blue-600" : "text-gray-300 hover:text-gray-500")}>
                                            {selectedItems.has(item.ml_id) ? <CheckSquare className="w-5 h-5" /> : <Square className="w-5 h-5" />}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <img src={item.thumbnail} alt="" className="w-12 h-12 object-cover rounded-md border border-gray-200" />
                                    </td>
                                    <td className="px-4 py-3">
                                        <a href={item.permalink} target="_blank" rel="noreferrer" className="font-medium text-gray-900 hover:underline line-clamp-2" title={item.title}>
                                            {item.title}
                                        </a>
                                        <div className="text-xs text-gray-500 mt-1 flex gap-2">
                                            <span>{item.ml_id}</span>
                                            <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase",
                                                item.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                            )}>
                                                {item.status}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            {item.logistic_type === 'fulfillment' && (
                                                <span className="flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold" title="Full">
                                                    <Zap className="w-3 h-3 fill-current" /> Full
                                                </span>
                                            )}
                                            {item.shipping_mode === 'me2' && (
                                                <span className="flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold" title="Mercado Envios">
                                                    <Truck className="w-3 h-3" /> ME2
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <div className="font-semibold">{item.sold_quantity}</div>
                                        <div className="text-xs text-gray-400">Total</div>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <div className={cn("font-bold text-lg",
                                            (item.days_without_sale || 0) > 60 ? "text-red-500" :
                                                (item.days_without_sale || 0) > 30 ? "text-yellow-600" : "text-gray-400"
                                        )}>
                                            {item.days_without_sale}
                                        </div>
                                        <div className="text-[10px] text-gray-400">dias</div>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium">
                                        R$ {Number(item.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button className="p-1 hover:bg-black/5 rounded text-gray-400 hover:text-gray-900">
                                            <MoreHorizontal className="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filteredItems?.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-center py-12 text-gray-400">
                                        Nenhum item encontrado com estes filtros.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="px-4 py-3 border-t border-gray-200 bg-gray-50 text-xs text-gray-500">
                    Mostrando {filteredItems?.length} de {items?.length} itens carregados.
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
                "px-3 py-1.5 rounded-md text-sm font-medium border transition-all flex items-center gap-2",
                active
                    ? "bg-gray-900 text-white border-gray-900 shadow-sm"
                    : "bg-white text-gray-600 border-gray-200 hover:bg-gray-50",
                className
            )}
        >
            {icon}
            {label}
        </button>
    )
}
