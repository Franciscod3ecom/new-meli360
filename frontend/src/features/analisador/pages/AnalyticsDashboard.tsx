import { useQuery } from '@tanstack/react-query'
import { api } from '../../../services/api'
import { useAuth } from '../../../context/AuthContext'
import {
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    ResponsiveContainer,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip
} from 'recharts'
import { TrendingUp, Package, Heart, DollarSign, Award } from 'lucide-react'
import { cn } from '../../../lib/utils'

interface AnalyticsData {
    overview: {
        total_items: number
        avg_health: number
        total_sales: number
        total_inventory_value: number
        active_items: number
        total_visits: number
        avg_visits: number
        avg_freight: number
        free_shipping_count: number
        conversion_rate: number
    }
    health_distribution: Array<{ health_range: string; count: number }>
    logistics_breakdown: Array<{ logistic_category: string; count: number }>
    status_distribution: Array<{ status: string; count: number }>
    regional_distribution: Array<{ name: string; value: number }>
    top_performers: Array<{
        ml_id: string
        title: string
        sold_quantity: number
        price: number
        secure_thumbnail: string
        health: number
        total_visits: number
    }>
}

const COLORS = {
    excellent: '#10b981', // green
    good: '#3b82f6', // blue
    regular: '#f59e0b', // amber
    critical: '#ef4444', // red
    full: '#fbbf24', // yellow
    me2: '#3b82f6', // blue
    others: '#6b7280' // gray
}

export default function AnalyticsDashboard() {
    const { user } = useAuth()

    const { data, isLoading, error } = useQuery<AnalyticsData>({
        queryKey: ['analytics', user?.id],
        queryFn: async () => {
            if (!user?.id) throw new Error('No user')
            return api.getAnalytics()
        },
        enabled: !!user?.id,
        refetchInterval: 60000 // Refresh every minute
    })

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-96">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p className="mt-4 text-gray-600">Carregando Analytics.</p>
                </div>
            </div>
        )
    }

    if (error || !data) {
        return (
            <div className="p-8 text-center text-red-500">
                <p className="font-bold">Erro ao carregar Analytics</p>
                <p className="text-sm mt-2">{(error as Error)?.message || 'Unknown error'}</p>
            </div>
        )
    }

    const avgHealthPercent = Math.round((data.overview.avg_health || 0) * 100)
    const formatMoney = (value: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)

    // Chart Data Preparation
    const healthChartData = data.health_distribution.map((item: { health_range: string; count: number }) => ({
        name: item.health_range,
        value: parseInt(item.count.toString())
    }))

    const logisticsChartData = data.logistics_breakdown.map((item: { logistic_category: string; count: number }) => ({
        name: item.logistic_category,
        value: parseInt(item.count.toString())
    }))

    const pieColors = (category: string) => {
        if (category.includes('Full')) return COLORS.full
        if (category.includes('ME2')) return COLORS.me2
        return COLORS.others
    }

    return (
        <div className="space-y-6 pb-8">
            {/* Header */}
            <div className="bg-gradient-to-r from-yellow-700 to-yellow-400 text-white p-6 rounded-lg shadow-lg">
                <h1 className="text-3xl font-bold flex items-center gap-3">
                    <TrendingUp className="w-8 h-8" />
                    Análise Profunda
                </h1>
                <p className="text-grey-100 mt-2">Insights do seu inventário em tempo real</p>
            </div>

            {/* Stats Cards */}
            {/* Stats Cards - Group 1: Inventory */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    icon={<Package className="w-6 h-6" />}
                    title="Total de Itens"
                    value={data.overview.total_items.toString()}
                    subtitle={`${data.overview.active_items} ativos`}
                    color="red"
                />
                <StatCard
                    icon={<Heart className="w-6 h-6" />}
                    title="Saúde Média"
                    value={`${avgHealthPercent}%`}
                    subtitle={avgHealthPercent >= 80 ? 'Excelente!' : avgHealthPercent >= 60 ? 'Bom' : 'Atenção'}
                    color={avgHealthPercent >= 80 ? 'green' : avgHealthPercent >= 60 ? 'blue' : 'red'}
                />
                <StatCard
                    icon={<TrendingUp className="w-6 h-6" />}
                    title="Visitas Totais"
                    value={data.overview.total_visits?.toLocaleString() || '0'}
                    subtitle={`${Math.round(data.overview.avg_visits || 0)} média/item`}
                    color="purple"
                />
                <StatCard
                    icon={<Award className="w-6 h-6" />}
                    title="Taxa Conversão"
                    value={`${(data.overview.conversion_rate || 0).toFixed(2)}%`}
                    subtitle="vendas sobre visitas"
                    color="yellow"
                />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    icon={<DollarSign className="w-6 h-6" />}
                    title="Valor Estoque"
                    value={formatMoney(parseFloat((data.overview.total_inventory_value || 0).toString()))}
                    subtitle="valor total disponível"
                    color="green"
                />
                <StatCard
                    icon={<Package className="w-6 h-6" />}
                    title="Frete Médio"
                    value={formatMoney(data.overview.avg_freight || 0)}
                    subtitle="custo nacional"
                    color="cyan"
                />
                <StatCard
                    icon={<Package className="w-6 h-6" />}
                    title="Frete Grátis"
                    value={data.overview.free_shipping_count.toString()}
                    subtitle="anúncios com frete grátis"
                    color="orange"
                />
                <StatCard
                    icon={<Award className="w-6 h-6" />}
                    title="Total Vendas"
                    value={data.overview.total_sales.toString()}
                    subtitle="unidades vendidas"
                    color="pink"
                />
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Health Distribution */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <Heart className="w-5 h-5 text-pink-500" />
                        Distribuição de Saúde dos Anúncios
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={healthChartData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                            <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                            <YAxis />
                            <Tooltip
                                contentStyle={{ backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px' }}
                            />
                            <Bar dataKey="value" fill="#3b82f6" radius={[8, 8, 0, 0]}>
                                {healthChartData.map((entry: { name: string; value: number }, index: number) => {
                                    let color = COLORS.critical
                                    if (entry.name.includes('Excelente')) color = COLORS.excellent
                                    else if (entry.name.includes('Boa')) color = COLORS.good
                                    else if (entry.name.includes('Regular')) color = COLORS.regular
                                    return <Cell key={`cell-${index}`} fill={color} />
                                })}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {/* Status Distribution */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <TrendingUp className="w-5 h-5 text-purple-500" />
                        Status dos Anúncios
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie
                                data={data.status_distribution.map((s: { status: string; count: number }) => ({ name: s.status, value: parseInt(s.count.toString()) }))}
                                cx="50%"
                                cy="50%"
                                innerRadius={60}
                                outerRadius={100}
                                paddingAngle={5}
                                dataKey="value"
                            >
                                {data.status_distribution.map((entry: { status: string; count: number }, index: number) => (
                                    <Cell key={`cell-${index}`} fill={entry.status === 'active' ? COLORS.excellent : entry.status === 'paused' ? COLORS.regular : COLORS.critical} />
                                ))}
                            </Pie>
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="flex justify-center gap-4 mt-2 text-xs text-gray-600">
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-[#10b981]" /> Ativos</span>
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-[#f59e0b]" /> Pausados</span>
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-[#ef4444]" /> Fechados/Outros</span>
                    </div>
                </div>

                {/* Logistics Breakdown */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <Package className="w-5 h-5 text-blue-500" />
                        Distribuição Logística
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie
                                data={logisticsChartData}
                                cx="50%"
                                cy="50%"
                                labelLine={false}
                                label={({ name, percent }) => `${name}: ${((percent || 0) * 100).toFixed(0)}%`}
                                outerRadius={100}
                                fill="#8884d8"
                                dataKey="value"
                            >
                                {logisticsChartData.map((entry: { name: string; value: number }, index: number) => (
                                    <Cell key={`cell-${index}`} fill={pieColors(entry.name)} />
                                ))}
                            </Pie>
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Regional Freight Chart */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <DollarSign className="w-5 h-5 text-cyan-500" />
                        Frete Médio por Região (R$)
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={data.regional_distribution}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                            <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                            <YAxis tickFormatter={(val) => `R$${val}`} />
                            <Tooltip
                                formatter={(value: number | string | undefined) => [formatMoney(Number(value) || 0), 'Frete Médio']}
                                contentStyle={{ backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px' }}
                            />
                            <Bar dataKey="value" fill="#06b6d4" radius={[8, 8, 0, 0]}>
                                {data.regional_distribution.map((_, index: number) => (
                                    <Cell key={`cell-${index}`} fill={['#06b6d4', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'][index % 5]} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* Top Performers */}
            <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Award className="w-5 h-5 text-yellow-500" />
                    Top 5 Mais Vendidos
                </h3>
                <div className="grid gap-3">
                    {data.top_performers.map((item: any, index: number) => (
                        <div
                            key={item.ml_id}
                            className="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <div className="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                #{(index + 1).toString()}
                            </div>
                            <img
                                src={item.secure_thumbnail || '/placeholder.png'}
                                alt=""
                                className="w-16 h-16 object-cover rounded-md border border-gray-200"
                            />
                            <div className="flex-1 min-w-0">
                                <p className="font-semibold text-gray-900 truncate">{item.title}</p>
                                <p className="text-sm text-gray-500">
                                    {item.sold_quantity} vendas • {item.total_visits || 0} visitas • {formatMoney(item.price)}
                                </p>
                            </div>
                            <div className="text-right">
                                <div className={cn(
                                    "text-xs font-bold px-2 py-1 rounded",
                                    (item.health || 0) >= 0.9 ? "bg-green-100 text-green-700" :
                                        (item.health || 0) >= 0.7 ? "bg-blue-100 text-blue-700" :
                                            "bg-yellow-100 text-yellow-700"
                                )}>
                                    {Math.round((item.health || 0) * 100)}% saúde
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    )
}

function StatCard({ icon, title, value, subtitle, color }: {
    icon: React.ReactNode
    title: string
    value: string
    subtitle: string
    color: 'blue' | 'green' | 'purple' | 'red' | 'yellow' | 'orange' | 'cyan' | 'pink'
}) {
    const colorClasses = {
        blue: 'from-blue-500 to-blue-600',
        green: 'from-green-500 to-green-600',
        purple: 'from-purple-500 to-purple-600',
        red: 'from-red-500 to-red-600',
        yellow: 'from-yellow-500 to-yellow-600',
        orange: 'from-orange-500 to-orange-600',
        cyan: 'from-cyan-500 to-cyan-600',
        pink: 'from-pink-500 to-pink-600'
    }

    return (
        <div className="bg-white rounded-lg shadow-md border border-gray-100 overflow-hidden">
            <div className={cn('bg-gradient-to-br p-4 text-white', colorClasses[color])}>
                <div className="flex items-center justify-between">
                    <div className="bg-white/20 p-2 rounded-lg backdrop-blur-sm">
                        {icon}
                    </div>
                </div>
                <div className="mt-3">
                    <p className="text-sm opacity-90">{title}</p>
                    <p className="text-2xl font-bold mt-1">{value}</p>
                    <p className="text-xs opacity-75 mt-1">{subtitle}</p>
                </div>
            </div>
        </div>
    )
}
