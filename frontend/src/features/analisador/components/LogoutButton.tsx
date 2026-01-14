import { LogOut } from 'lucide-react'
import { useAuth } from '../../../context/AuthContext'

export function LogoutButton() {
    const { logout } = useAuth()

    return (
        <button
            onClick={logout}
            title="Sair do Sistema"
            className="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
        >
            <LogOut className="w-5 h-5" />
        </button>
    )
}
