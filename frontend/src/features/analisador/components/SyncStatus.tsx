
import { useEffect, useState } from 'react'
import { RefreshCw, CheckCircle, AlertTriangle } from 'lucide-react'
import { supabase } from '../../../lib/supabase'

interface AccountStatus {
    ml_user_id: string
    sync_status: 'IDLE' | 'REQUESTED' | 'SYNCING' | 'COMPLETED' | 'ERROR'
    sync_last_message: string
    sync_last_run_at: string
}

export function SyncStatus() {
    const [status, setStatus] = useState<AccountStatus | null>(null)

    useEffect(() => {
        // Initial fetch
        fetchStatus()

        // Real-time subscription
        const channel = supabase
            .channel('accounts_changes')
            .on(
                'postgres_changes',
                { event: '*', schema: 'public', table: 'accounts' },
                (payload) => {
                    setStatus(payload.new as AccountStatus)
                }
            )
            .subscribe()

        return () => { supabase.removeChannel(channel) }
    }, [])

    const fetchStatus = async () => {
        const { data } = await supabase.from('accounts').select('*').limit(1).single()
        if (data) setStatus(data)
    }

    if (!status || status.sync_status === 'IDLE') return null

    const isSyncing = status.sync_status === 'SYNCING' || status.sync_status === 'REQUESTED'
    const isError = status.sync_status === 'ERROR'
    const isCompleted = status.sync_status === 'COMPLETED'

    return (
        <div className={`
      fixed bottom-0 left-0 right-0 z-40
      border-t 
      ${isSyncing ? 'bg-blue-50 border-blue-200 text-blue-800' : ''}
      ${isError ? 'bg-red-50 border-red-200 text-red-800' : ''}
      ${isCompleted ? 'bg-green-50 border-green-200 text-green-800' : ''}
      transition-all duration-500 ease-in-out px-6 py-3
    `}>
            <div className="max-w-7xl mx-auto flex items-center justify-between text-sm">
                <div className="flex items-center gap-3">
                    {isSyncing && <RefreshCw className="w-4 h-4 animate-spin" />}
                    {isError && <AlertTriangle className="w-4 h-4" />}
                    {isCompleted && <CheckCircle className="w-4 h-4" />}

                    <span className="font-semibold">
                        {isSyncing ? 'Sincronizando...' : isError ? 'Erro na Sincronização' : 'Sincronizado'}
                    </span>
                    <span className="hidden sm:inline text-opacity-80 mx-2">|</span>
                    <span className="font-mono text-xs opacity-80 truncate max-w-md">
                        {status.sync_last_message}
                    </span>
                </div>

                <div className="text-xs opacity-70">
                    Última atualização: {new Date(status.sync_last_run_at).toLocaleTimeString()}
                </div>
            </div>
        </div>
    )
}
