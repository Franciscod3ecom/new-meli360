import React, { useState } from 'react'
import { api } from '../../services/api'
import { toast } from 'sonner'
import { X, Loader2, Lock, ShieldCheck } from 'lucide-react'

interface ChangePasswordModalProps {
    isOpen: boolean
    onClose: () => void
}

export default function ChangePasswordModal({ isOpen, onClose }: ChangePasswordModalProps) {
    const [isLoading, setIsLoading] = useState(false)
    const [formData, setFormData] = useState({
        currentPassword: '',
        newPassword: '',
        confirmPassword: ''
    })

    if (!isOpen) return null

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()

        if (formData.newPassword !== formData.confirmPassword) {
            toast.error('A nova senha e a confirmação não coincidem')
            return
        }

        if (formData.newPassword.length < 6) {
            toast.error('A nova senha deve ter pelo menos 6 caracteres')
            return
        }

        setIsLoading(true)
        try {
            await api.changePassword(formData.currentPassword, formData.newPassword)
            toast.success('Senha alterada com sucesso!')
            onClose()
            setFormData({ currentPassword: '', newPassword: '', confirmPassword: '' })
        } catch (error: any) {
            toast.error(error.message || 'Erro ao alterar senha')
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div className="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-blue-100 rounded-lg">
                            <Lock className="w-5 h-5 text-blue-600" />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900">Alterar Minha Senha</h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-400 hover:text-gray-600"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-gray-500 uppercase flex items-center gap-1">
                            Senha Atual
                        </label>
                        <input
                            type="password"
                            required
                            value={formData.currentPassword}
                            onChange={(e) => setFormData({ ...formData, currentPassword: e.target.value })}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all transition-colors"
                            placeholder="Digite sua senha atual"
                        />
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-gray-500 uppercase flex items-center gap-1">
                            Nova Senha
                        </label>
                        <input
                            type="password"
                            required
                            value={formData.newPassword}
                            onChange={(e) => setFormData({ ...formData, newPassword: e.target.value })}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all transition-colors"
                            placeholder="Mínimo 6 caracteres"
                        />
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-gray-500 uppercase flex items-center gap-1">
                            Confirmar Nova Senha
                        </label>
                        <input
                            type="password"
                            required
                            value={formData.confirmPassword}
                            onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all transition-colors"
                            placeholder="Repita a nova senha"
                        />
                    </div>

                    <div className="pt-4 flex gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            {isLoading ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <ShieldCheck className="w-4 h-4" />
                            )}
                            Salvar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}
