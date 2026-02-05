import { useEffect, useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import { LayoutDashboard, ShoppingBag, ArrowRight, UserPlus, Loader2, LogIn } from 'lucide-react'
import { toast } from 'sonner'
import { api } from '../../../services/api'

export default function Login() {
    const { login, isAuthenticated, checkSession } = useAuth()
    const [searchParams] = useSearchParams()
    const navigate = useNavigate()
    const [isLoading, setIsLoading] = useState(false)
    const [formData, setFormData] = useState({
        email: '',
        password: ''
    })

    const authSuccess = searchParams.get('auth_success')

    useEffect(() => {
        if (authSuccess) {
            checkSession().then(() => {
                navigate('/inventory', { replace: true })
            })
        }
    }, [authSuccess, checkSession, navigate])

    useEffect(() => {
        if (isAuthenticated) {
            navigate('/inventory', { replace: true })
        }
    }, [isAuthenticated, navigate])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }))
    }

    const handleNativeLogin = async (e: React.FormEvent) => {
        e.preventDefault()
        console.log('🔵 Login iniciado')

        if (!formData.email || !formData.password) {
            toast.error('Preencha email e senha.')
            return
        }

        setIsLoading(true)
        try {
            console.log('🔵 Chamando api.loginNative...')
            const result = await api.loginNative(formData.email, formData.password)
            console.log('✅ Login sucesso:', result)

            toast.success('Login realizado com sucesso!')

            console.log('🔵 Chamando checkSession...')
            await checkSession()
            console.log('✅ checkSession completo')

            console.log('🔵 Navegando para /inventory...')
            navigate('/inventory', { replace: true })
        } catch (error: any) {
            console.error('❌ Erro no login:', error)
            toast.error(error.message || 'Falha no login. Verifique suas credenciais.')
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div className="min-h-screen w-full bg-white flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
            {/* Decorative Background Elements */}
            <div className="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
                <div className="absolute top-0 left-1/4 w-96 h-96 bg-gray-100 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
                <div className="absolute top-0 right-1/4 w-96 h-96 bg-gray-50 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
            </div>

            <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
                <div className="flex justify-center mb-6">
                    <div className="w-16 h-16 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-2xl shadow-lg flex items-center justify-center transform rotate-12 transition-transform hover:rotate-0">
                        <LayoutDashboard className="w-8 h-8 text-white" />
                    </div>
                </div>

                <h2 className="mt-2 text-center text-3xl font-extrabold text-gray-900 tracking-tight">
                    Meli360 <span className="font-light text-gray-400">Analisador</span>
                </h2>
                <p className="mt-2 text-center text-sm text-gray-600">
                    Gerencie seu inventário do Mercado Livre com inteligência.
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
                <div className="bg-white py-8 px-4 shadow-xl sm:rounded-lg sm:px-10 border border-gray-100">

                    {/* Native Login Form */}
                    <form className="space-y-6" onSubmit={handleNativeLogin}>
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">E-mail</label>
                            <div className="mt-1">
                                <input id="email" name="email" type="email" autoComplete="email" required value={formData.email} onChange={handleChange} className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                            </div>
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700">Senha</label>
                            <div className="mt-1">
                                <input id="password" name="password" type="password" autoComplete="current-password" required value={formData.password} onChange={handleChange} className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                            </div>
                        </div>

                        <div className="flex items-center justify-end">
                            <Link to="/forgot-password" className="text-sm font-medium text-blue-600 hover:text-blue-500">
                                Esqueci minha senha
                            </Link>
                        </div>

                        <div>
                            <button type="submit" disabled={isLoading} className="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 transition-all">
                                {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : <LogIn className="w-4 h-4" />}
                                Entrar
                            </button>
                        </div>
                    </form>

                    <div className="mt-6">
                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-gray-200" />
                            </div>
                            <div className="relative flex justify-center text-sm">
                                <span className="px-2 bg-white text-gray-500">Ou continuar com</span>
                            </div>
                        </div>

                        <div className="mt-6">
                            <button
                                onClick={login}
                                className="w-full flex justify-center items-center gap-3 py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#2D3277] hover:bg-[#232766] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-0.5"
                            >
                                <div className="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <ShoppingBag className="w-3 h-3 text-[#2D3277]" />
                                </div>
                                Mercado Livre (OAuth)
                                <ArrowRight className="w-4 h-4 opacity-70" />
                            </button>
                        </div>
                    </div>

                    <div className="mt-6">
                        <Link
                            to="/register"
                            className="w-full flex justify-center items-center gap-2 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all"
                        >
                            <UserPlus className="w-4 h-4 text-gray-500" />
                            Criar Nova Conta
                        </Link>
                    </div>

                </div>
            </div>
        </div>
    )
}
