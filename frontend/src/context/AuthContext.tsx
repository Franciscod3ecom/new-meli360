import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { api } from '../services/api'

interface User {
    id: string
    ml_user_id: string
    nickname: string
}

interface AuthContextType {
    user: User | null
    isAuthenticated: boolean
    isLoading: boolean
    login: () => void
    logout: () => void
    checkSession: () => Promise<void>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null)
    const [isAuthenticated, setIsAuthenticated] = useState(false)
    const [isLoading, setIsLoading] = useState(true)

    const checkSession = async () => {
        try {
            const data = await api.checkAuth()
            if (data.authenticated && data.user) {
                setUser(data.user)
                setIsAuthenticated(true)
            } else {
                setUser(null)
                setIsAuthenticated(false)
            }
        } catch (error) {
            console.error('Session check failed', error)
            setIsAuthenticated(false)
        } finally {
            setIsLoading(false)
        }
    }

    useEffect(() => {
        checkSession()
    }, [])

    const login = () => {
        api.getAuthUrl()
    }

    const logout = () => {
        // For now, logout is just client-side state clear + maybe optional backend session destroy if we had one.
        // Since we rely on 'me.php' checking database existence or cookies, 
        // real logout would involve clearing cookies or session endpoint.
        // For this implementation, we'll assume session is persistent until cleared? 
        // Actually, 'me.php' checks if account exists in DB. 
        // To 'logout' in this single-tenant context might mean just reloading or clearing local state?
        // Let's keep it simple: just redirect or reload. 
        // If we wanted to "forget" the user, we'd need a backend logout.
        // We'll impl simple state clear for UI.
        setUser(null)
        setIsAuthenticated(false)
        window.location.href = '/'
    }

    return (
        <AuthContext.Provider value={{ user, isAuthenticated, isLoading, login, logout, checkSession }}>
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
