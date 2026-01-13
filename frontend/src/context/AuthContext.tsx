import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react'
import { api } from '../services/api'
import { toast } from 'sonner'

interface User {
    id?: string
    ml_user_id: string
    nickname: string
}

interface Account {
    ml_user_id: string
    nickname: string
    sync_status?: string
    updated_at?: string
}

interface AuthContextType {
    user: User | null
    accounts: Account[]
    isAuthenticated: boolean
    isLoading: boolean
    login: () => void
    logout: () => void
    checkSession: () => Promise<void>
    switchAccount: (userId: string) => Promise<void>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null)
    const [accounts, setAccounts] = useState<Account[]>([])
    const [isAuthenticated, setIsAuthenticated] = useState(false)
    const [isLoading, setIsLoading] = useState(true)

    const checkSession = useCallback(async () => {
        try {
            // 1. Check current session
            const sessionData = await api.checkAuth()

            if (sessionData.authenticated && sessionData.user) {
                setUser(sessionData.user)
                setIsAuthenticated(true)

                // 2. Fetch available accounts if authenticated
                try {
                    const accountsData = await api.getAccounts()
                    if (accountsData.accounts) {
                        setAccounts(accountsData.accounts)
                    }
                } catch (accError) {
                    console.error('Failed to load accounts list', accError)
                }

            } else {
                setUser(null)
                setAccounts([])
                setIsAuthenticated(false)
            }
        } catch (error) {
            console.error('Session check failed', error)
            setIsAuthenticated(false)
        } finally {
            setIsLoading(false)
        }
    }, [])

    useEffect(() => {
        checkSession()
    }, [checkSession])

    const login = () => {
        api.getAuthUrl()
    }

    const logout = () => {
        window.location.href = `${import.meta.env.VITE_BACKEND_URL}/auth/logout.php`
    }

    const switchAccount = async (targetUserId: string) => {
        try {
            await api.switchAccount(targetUserId)
            toast.success('Conta alterada com sucesso!')
            // Reload to refresh all data with new session
            window.location.reload()
        } catch (error) {
            console.error('Switch failed', error)
            toast.error('Erro ao trocar de conta.')
        }
    }

    return (
        <AuthContext.Provider value={{ user, accounts, isAuthenticated, isLoading, login, logout, checkSession, switchAccount }}>
            {children}
        </AuthContext.Provider>
    )
}

export function useAuth() {
    const context = useContext(AuthContext)
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider')
    }
    return context
}
