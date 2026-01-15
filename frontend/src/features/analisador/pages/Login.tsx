import { useEffect } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import { LayoutDashboard, ShoppingBag, ArrowRight, UserPlus } from 'lucide-react'

export default function Login() {
    const { login, isAuthenticated, checkSession } = useAuth()
    const [searchParams] = useSearchParams()
    const navigate = useNavigate()

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

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">

            {/* Decorative Background Elements */}
            <div className="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
                <div className="absolute top-0 left-1/4 w-96 h-96 bg-yellow-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                <div className="absolute top-0 right-1/4 w-96 h-96 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
                <div className="absolute -bottom-32 left-1/3 w-96 h-96 bg-pink-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
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
                    Gerencie seu inventário do Mercado Livre com inteligência e estratégia.
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
                <div className="bg-white py-8 px-4 shadow-xl sm:rounded-lg sm:px-10 border border-gray-100">

                    <div className="space-y-6">
                        <div>
                            <button
                                onClick={login}
                                className="w-full flex justify-center items-center gap-3 py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#2D3277] hover:bg-[#232766] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-0.5"
                            >
                                <div className="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <ShoppingBag className="w-3 h-3 text-[#2D3277]" />
                                </div>
                                Conectar com Mercado Livre
                                <ArrowRight className="w-4 h-4 opacity-70" />
                            </button>
                        </div>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-gray-200" />
                            </div>
                            <div className="relative flex justify-center text-sm">
                                <span className="px-2 bg-white text-gray-500">
                                    Ou
                                </span>
                            </div>
                        </div>

                        <div>
                            <a
                                href="https://docs.google.com/forms/d/e/1FAIpQLSfwZgnOdJmN5ovEe0KN3f-JL5rFB6Q0JQIJp2SZOdZPDDbEyw/viewform"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="w-full flex justify-center items-center gap-3 py-3 px-4 border-2 border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:-translate-y-0.5"
                            >
                                <UserPlus className="w-5 h-5 text-blue-600" />
                                Criar Nova Conta
                                <ArrowRight className="w-4 h-4 opacity-70" />
                            </a>
                            <p className="mt-2 text-xs text-center text-gray-500">
                                Ainda não tem acesso? Cadastre-se agora!
                            </p>
                        </div>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-gray-200" />
                            </div>
                            <div className="relative flex justify-center text-sm">
                                <span className="px-2 bg-white text-gray-500">
                                    Segurança e Integração
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4 text-xs text-gray-500 text-center">
                            <div className="bg-gray-50 p-3 rounded-lg">
                                <span className="block font-semibold text-gray-900 mb-1">Dados Seguros</span>
                                Suas credenciais são criptografadas via OAuth.
                            </div>
                            <div className="bg-gray-50 p-3 rounded-lg">
                                <span className="block font-semibold text-gray-900 mb-1">Sync Automático</span>
                                Seu estoque atualizado em tempo real.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    )
}
