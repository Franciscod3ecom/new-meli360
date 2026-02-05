import { useAuth } from '../../../context/AuthContext'
import { api } from '../../../services/api'
import { Check, ChevronDown, LogOut, PlusCircle, UserCircle, Trash2, Lock } from 'lucide-react'
import { useState, useRef, useEffect } from 'react'
import { cn } from '../../../lib/utils'
import { toast } from 'sonner'
import ChangePasswordModal from '../../auth/ChangePasswordModal'

export function AccountSwitcher() {
    const { user, accounts, switchAccount, login, logout, checkSession } = useAuth()
    const [isOpen, setIsOpen] = useState(false)
    const [isChangePasswordOpen, setIsChangePasswordOpen] = useState(false)
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
                            <div key={account.ml_user_id} className="group relative">
                                <button
                                    onClick={() => {
                                        if (account.ml_user_id !== user.ml_user_id) {
                                            switchAccount(account.ml_user_id)
                                        }
                                        setIsOpen(false)
                                    }}
                                    className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center justify-between"
                                >
                                    <div className="flex items-center gap-2 overflow-hidden">
                                        <span className={cn(
                                            "truncate",
                                            account.ml_user_id === user.ml_user_id ? 'font-semibold' : ''
                                        )}>
                                            {account.nickname}
                                        </span>
                                        {account.ml_user_id === user.ml_user_id && (
                                            <Check className="w-4 h-4 text-green-500 flex-shrink-0" />
                                        )}
                                    </div>
                                </button>
                                <button
                                    onClick={async (e) => {
                                        e.stopPropagation()
                                        if (window.confirm(`⚠️ AVISO CRÍTICO: Tem certeza que deseja EXCLUIR permanentemente a conta ${account.nickname}? \n\nIsso apagará TODOS os dados de anúncios, fretes e histórico desta conta do nosso banco de dados. Esta ação não pode ser desfeita.`)) {
                                            try {
                                                await api.deleteAccount(account.id)
                                                toast.success("Conta e dados excluídos com sucesso.")
                                                checkSession() // Refresh accounts list
                                            } catch (err) {
                                                console.error(err)
                                                toast.error("Erro ao excluir conta.")
                                            }
                                        }
                                    }}
                                    className="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Excluir conta e dados permanentemente"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            </div>
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
                            onClick={() => {
                                setIsChangePasswordOpen(true)
                                setIsOpen(false)
                            }}
                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                        >
                            <Lock className="w-4 h-4 text-gray-400" />
                            Alterar Senha
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

            <ChangePasswordModal
                isOpen={isChangePasswordOpen}
                onClose={() => setIsChangePasswordOpen(false)}
            />
        </div>
    )
}
