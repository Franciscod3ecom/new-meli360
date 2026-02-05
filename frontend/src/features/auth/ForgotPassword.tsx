import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../services/api'
import { toast } from 'sonner'
import { Mail, ArrowLeft, Loader2 } from 'lucide-react'

export default function ForgotPassword() {
    const [email, setEmail] = useState('')
    const [isLoading, setIsLoading] = useState(false)
    const [isSent, setIsSent] = useState(false)

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setIsLoading(true)

        try {
            await api.forgotPassword(email)
            setIsSent(true)
            toast.success('Solicitação enviada!')
        } catch (error: any) {
            toast.error(error.message || 'Erro ao processar solicitação')
        } finally {
            setIsLoading(false)
        }
    }

    if (isSent) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-gray-100">
                    <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600">
                        <Mail className="w-8 h-8" />
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Verifique seu e-mail</h2>
                    <p className="text-gray-600 mb-8">
                        Se o e-mail <strong>{email}</strong> estiver cadastrado, você receberá um link para redefinir sua senha em instantes.
                    </p>
                    <Link
                        to="/login"
                        className="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Voltar para o Login
                    </Link>
                </div>
            </div>
        )
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <div className="text-center mb-8">
                    <h2 className="text-3xl font-bold text-gray-900">Recuperar Senha</h2>
                    <p className="text-gray-600 mt-2">Ops! Acontece com os melhores.</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                            E-mail da sua conta
                        </label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                                placeholder="exemplo@email.com"
                                required
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={isLoading}
                        className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="w-5 h-5 animate-spin" />
                                Processando...
                            </>
                        ) : (
                            'Enviar Link de Recuperação'
                        )}
                    </button>

                    <div className="text-center">
                        <Link
                            to="/login"
                            className="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-blue-600 transition-colors"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            Voltar para o Login
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    )
}
