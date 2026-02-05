import { useState } from 'react'
import { Link, useSearchParams, useNavigate } from 'react-router-dom'
import { api } from '../../services/api'
import { toast } from 'sonner'
import { Lock, Eye, EyeOff, Loader2, CheckCircle } from 'lucide-react'

export default function ResetPassword() {
    const [searchParams] = useSearchParams()
    const navigate = useNavigate()
    const token = searchParams.get('token')

    const [password, setPassword] = useState('')
    const [confirmPassword, setConfirmPassword] = useState('')
    const [showPassword, setShowPassword] = useState(false)
    const [isLoading, setIsLoading] = useState(false)
    const [isSuccess, setIsSuccess] = useState(false)

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()

        if (!token) {
            toast.error('Token inválido ou ausente')
            return
        }

        if (password.length < 8) {
            toast.error('A senha deve ter no mínimo 8 caracteres')
            return
        }

        if (password !== confirmPassword) {
            toast.error('As senhas não coincidem')
            return
        }

        setIsLoading(true)

        try {
            await api.resetPassword(token, password)
            setIsSuccess(true)
            toast.success('Senha atualizada com sucesso!')
            setTimeout(() => navigate('/login'), 3000)
        } catch (error: any) {
            toast.error(error.message || 'Erro ao redefinir senha')
        } finally {
            setIsLoading(false)
        }
    }

    if (!token) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-gray-100">
                    <h2 className="text-2xl font-bold text-red-600 mb-2">Token Inválido</h2>
                    <p className="text-gray-600 mb-8">O link de recuperação está incompleto ou expirado.</p>
                    <Link to="/forgot-password" className="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg">Solicitar novo link</Link>
                </div>
            </div>
        )
    }

    if (isSuccess) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-gray-100">
                    <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600">
                        <CheckCircle className="w-8 h-8" />
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Senha Atualizada!</h2>
                    <p className="text-gray-600 mb-8">
                        Sua nova senha foi salva com sucesso. Você será redirecionado para o login em instantes.
                    </p>
                    <Link
                        to="/login"
                        className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-all"
                    >
                        Fazer Login Agora
                    </Link>
                </div>
            </div>
        )
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <div className="text-center mb-8">
                    <h2 className="text-3xl font-bold text-gray-900">Nova Senha</h2>
                    <p className="text-gray-600 mt-2">Escolha uma senha forte desta vez!</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Nova Senha
                        </label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full pl-10 pr-12 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Confirmar Senha
                        </label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                className="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                required
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={isLoading}
                        className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="w-5 h-5 animate-spin" />
                                Salvando...
                            </>
                        ) : (
                            'Salvar Nova Senha'
                        )}
                    </button>
                </form>
            </div>
        </div>
    )
}
