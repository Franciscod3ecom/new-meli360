import { useQuery } from '@tanstack/react-query'
import { supabase } from '@/lib/supabase'
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"
import { Truck, Zap, AlertTriangle } from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'

interface Item {
    id: string
    ml_id: string
    title: string
    price: number
    thumbnail: string
    shipping_mode: string
    logistic_type: string
    sold_quantity: number
    last_sale_date: string | null
    days_without_sale: number // From view or calculated
}

export default function Inventory() {
    const { data: items, isLoading, error } = useQuery({
        queryKey: ['items'],
        queryFn: async () => {
            // NOTE: Using the view 'items_view' if created, or calculating here.
            // Since we defined a view in schema.sql, let's try to fetch from it.
            // If view doesn't exist, we fall back to 'items' table and calculate safely.

            const { data, error } = await supabase
                .from('items') // Or 'items_view' if using the view
                .select('*')
                .order('last_sale_date', { ascending: true })
                .limit(100)

            if (error) throw error

            // Client-side calculation for robustness if View is missing or not queried
            return data.map((item: any) => {
                const lastSale = item.last_sale_date ? new Date(item.last_sale_date) : (item.date_created ? new Date(item.date_created) : new Date())
                const days = Math.floor((new Date().getTime() - lastSale.getTime()) / (1000 * 3600 * 24))
                return { ...item, days_without_sale: days }
            }) as Item[]
        }
    })

    if (isLoading) return <div className="p-8 text-center">Carregando inventário...</div>
    if (error) return <div className="p-8 text-center text-red-500">Erro ao carregar items: {(error as Error).message}</div>

    return (
        <div className="container mx-auto py-10">
            <h1 className="text-3xl font-bold mb-6">Inventário 360</h1>

            <div className="rounded-md border">
                <Table>
                    <TableCaption>Lista de anúncios sincronizados.</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[100px]">Foto</TableHead>
                            <TableHead>Título</TableHead>
                            <TableHead>Preço</TableHead>
                            <TableHead>Logística</TableHead>
                            <TableHead className="text-right">Dias s/ Venda</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {items?.map((item) => {
                            const isStagnant = item.days_without_sale > 60
                            const isWarning = item.days_without_sale > 30 && !isStagnant

                            return (
                                <TableRow
                                    key={item.id}
                                    className={
                                        isStagnant ? "bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-900/30" :
                                            isWarning ? "bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-950/20" : ""
                                    }
                                >
                                    <TableCell>
                                        <img
                                            src={item.thumbnail}
                                            alt={item.title}
                                            className="w-12 h-12 object-cover rounded-md"
                                        />
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        <div className="flex flex-col">
                                            <span>{item.title}</span>
                                            <span className="text-xs text-muted-foreground">{item.ml_id}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        R$ {Number(item.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {item.logistic_type === 'fulfillment' && (
                                                <Zap className="h-5 w-5 text-yellow-500 fill-yellow-500" title="Full" />
                                            )}
                                            {item.shipping_mode === 'me2' && (
                                                <Truck className="h-5 w-5 text-green-600" title="Mercado Envios" />
                                            )}
                                            <span className="text-xs text-muted-foreground capitalize">
                                                {item.logistic_type?.replace('_', ' ')}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <span className={isStagnant ? "text-red-600 font-bold" : ""}>
                                                {item.days_without_sale} dias
                                            </span>
                                            {isStagnant && <AlertTriangle className="h-4 w-4 text-red-600" />}
                                        </div>
                                        {item.last_sale_date && (
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(item.last_sale_date).toLocaleDateString('pt-BR')}
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            )
                        })}
                    </TableBody>
                </Table>
            </div>
        </div>
    )
}
