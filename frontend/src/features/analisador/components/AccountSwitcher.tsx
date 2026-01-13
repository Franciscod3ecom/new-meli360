import { useAuth } from '../../../context/AuthContext'
import { Check, ChevronDown, LogOut, PlusCircle, UserCircle } from 'lucide-react'
import { useState, useRef, useEffect } from 'react'

export function AccountSwitcher() {
    const { user, accounts, switchAccount, login, logout } = useAuth()
    const [isOpen, setIsOpen] = useState(false)
    const dropdownRef = useRef<HTMLDivElement>(null)

    // Close dropdown when clicking outside
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false)
            }
        }
        document.addEventListener("mousedown", handleClickOutside)
        return () => {
            document.removeEventListener("mousedown", handleClickOutside)
        }
    }, [])

    if (!user) return null

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
            >
                <UserCircle className="w-5 h-5 text-gray-400" />
                <span className="max-w-[120px] truncate">{user.nickname || 'Usuario'}</span>
                <ChevronDown className="w-4 h-4 text-gray-400" />
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5 z-50">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <p className="text-xs text-gray-500 uppercase font-semibold">Conta Atual</p>
                        <p className="text-sm font-medium text-gray-900 truncate">{user.nickname}</p>
                    </div>

                    <div className="max-h-60 overflow-y-auto">
                        {accounts.map((account) => (
                            <button
                                key={account.ml_user_id}
                                onClick={() => {
                                    if (account.ml_user_id !== user.ml_user_id) {
                                        switchAccount(account.ml_user_id)
                                    }
                                    setIsOpen(false)
                                }}
                                className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center justify-between"
                            >
                                <span className={account.ml_user_id === user.ml_user_id ? 'font-semibold' : ''}>
                                    {account.nickname}
                                </span>
                                {account.ml_user_id === user.ml_user_id && (
                                    <Check className="w-4 h-4 text-green-500" />
                                )}
                            </button>
                        ))}
                    </div>

                    <div className="border-t border-gray-100 pt-1">
                        <button
                            onClick={() => {
                                login() // Trigger new OAuth flow
                                setIsOpen(false)
                            }}
                            className="w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 flex items-center gap-2"
                        >
                            <PlusCircle className="w-4 h-4" />
                            Adicionar Conta
                        </button>

                        <button
                            onClick={() => logout()}
                            className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                        >
                            <LogOut className="w-4 h-4" />
                            Sair
                        </button>
                    </div>
                </div>
            )}
        </div>
    )
}
