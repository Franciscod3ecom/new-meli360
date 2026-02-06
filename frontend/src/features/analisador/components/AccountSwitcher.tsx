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
                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-800 rounded-xl hover:bg-neutral-50 dark:hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition-all shadow-sm"
            >
                <UserCircle className="w-5 h-5 text-neutral-400 dark:text-neutral-500" />
                <span className="max-w-[120px] truncate">{user.nickname || 'Usuario'}</span>
                <ChevronDown className="w-4 h-4 text-neutral-400" />
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-56 bg-white dark:bg-black rounded-xl shadow-2xl py-2 ring-1 ring-black ring-opacity-5 dark:ring-neutral-800 z-50 border border-transparent dark:border-neutral-800 transition-all animate-scale-in">
                    <div className="px-4 py-2 border-b border-neutral-100 dark:border-neutral-800 mb-1">
                        <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-wider">Conta Atual</p>
                        <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-50 truncate">{user.nickname}</p>
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
                                    className="w-full text-left px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-900 flex items-center justify-between transition-colors"
                                >
                                    <div className="flex items-center gap-2 overflow-hidden">
                                        <span className={cn(
                                            "truncate",
                                            account.ml_user_id === user.ml_user_id ? 'font-semibold text-brand-600 dark:text-brand-400' : ''
                                        )}>
                                            {account.nickname}
                                        </span>
                                        {account.ml_user_id === user.ml_user_id && (
                                            <Check className="w-4 h-4 text-brand-500 flex-shrink-0" />
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

                    <div className="border-t border-neutral-100 dark:border-neutral-800 mt-1 pt-1">
                        <button
                            onClick={() => {
                                login() // Trigger new OAuth flow
                                setIsOpen(false)
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/40 flex items-center gap-2 transition-colors"
                        >
                            <PlusCircle className="w-4 h-4" />
                            Adicionar Conta
                        </button>

                        <button
                            onClick={() => {
                                setIsChangePasswordOpen(true)
                                setIsOpen(false)
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-900 flex items-center gap-2 transition-colors"
                        >
                            <Lock className="w-4 h-4 text-neutral-400 dark:text-neutral-500" />
                            Alterar Senha
                        </button>

                        <button
                            onClick={() => logout()}
                            className="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/40 flex items-center gap-2 transition-colors"
                        >
                            <LogOut className="w-4 h-4" />
                            Sair do Portal
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
