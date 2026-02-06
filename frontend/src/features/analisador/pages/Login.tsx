import { useEffect, useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import { LayoutDashboard, ShoppingBag, ArrowRight, UserPlus, LogIn } from 'lucide-react'
import { toast } from 'sonner'
import { api } from '../../../services/api'
import Button from '../../../components/ui/Button'

export default function Login() {
    const { login, isAuthenticated, checkSession } = useAuth()
    const [searchParams] = useSearchParams()
    const navigate = useNavigate()
    const [isLoading, setIsLoading] = useState(false)
    const [loginError, setLoginError] = useState<string | null>(null)
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
        if (loginError) setLoginError(null)
    }

    const handleNativeLogin = async (e: React.FormEvent) => {
        e.preventDefault()

        if (!formData.email || !formData.password) {
            toast.error('Preencha email e senha.')
            return
        }

        setIsLoading(true)
        setLoginError(null)
        try {
            await api.loginNative(formData.email, formData.password)
            toast.success('Bem-vindo de volta!', {
                description: 'Acessando seu painel analítico...'
            })
            await checkSession()
            navigate('/inventory', { replace: true })
        } catch (error: any) {
            console.error('Login error:', error)
            setLoginError('Usuário ou senha incorretos. Tente novamente.')
            toast.error('Acesso Negado', {
                description: 'E-mail ou senha incorretos. Verifique os dados e tente novamente.',
                duration: 5000,
            })
            // Temporary vibration effect for UI feedback
            const container = document.getElementById('login-card')
            if (container) {
                container.classList.add('animate-shake')
                setTimeout(() => container.classList.remove('animate-shake'), 500)
            }
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div className="min-h-screen w-full bg-neutral-0 dark:bg-neutral-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden transition-colors">
            {/* Liquid Glass Background Elements */}
            <div className="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
                <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-200/20 dark:bg-brand-900/10 rounded-full blur-[120px] animate-pulse"></div>
                <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-accent-200/20 dark:bg-accent-900/10 rounded-full blur-[120px] animation-delay-2000"></div>
            </div>

            <main className="sm:mx-auto sm:w-full sm:max-w-md relative z-10 animate-fade-in">
                <div className="flex justify-center mb-8">
                    <div className="w-20 h-20 bg-brand-500 rounded-3xl shadow-xl dark:shadow-brand-900/20 flex items-center justify-center transform hover:scale-105 transition-all duration-300">
                        <LayoutDashboard className="w-10 h-10 text-neutral-900" />
                    </div>
                </div>

                <div className="text-center mb-10">
                    <h2 className="text-4xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-50 mb-2">
                        Meli360 <span className="font-light text-neutral-400">Analisador</span>
                    </h2>
                    <p className="text-lg text-neutral-600 dark:text-neutral-400">
                        A inteligência que seu inventário precisa.
                    </p>
                </div>

                <div id="login-card" className="glass rounded-3xl p-8 sm:p-10 shadow-2xl transition-transform">
                    <form className="space-y-5" onSubmit={handleNativeLogin}>
                        <div className="space-y-2">
                            <label htmlFor="email" className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 ml-1">
                                E-mail de Acesso
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={formData.email}
                                onChange={handleChange}
                                placeholder="exemplo@email.com"
                                className="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-neutral-50 placeholder:text-neutral-400 focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 focus:ring-1 focus:ring-brand-500 transition-all"
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between ml-1">
                                <label htmlFor="password" className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    Sua Senha
                                </label>
                                <Link to="/forgot-password" virtual-link="true" className="text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 transition-colors">
                                    Esqueceu a senha?
                                </Link>
                            </div>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                value={formData.password}
                                onChange={handleChange}
                                placeholder="••••••••"
                                className="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-neutral-50 placeholder:text-neutral-400 focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 focus:ring-1 focus:ring-brand-500 transition-all"
                            />
                        </div>

                        <Button
                            type="submit"
                            isLoading={isLoading}
                            className="w-full mt-4"
                            icon={<LogIn className="w-5 h-5" />}
                        >
                            Logar no Analisador
                        </Button>

                        {loginError && (
                            <p className="mt-3 text-center text-sm font-semibold text-red-500 animate-fade-in flex items-center justify-center gap-2">
                                <span className="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]" />
                                {loginError}
                            </p>
                        )}
                    </form>

                    <div className="mt-8 relative">
                        <div className="absolute inset-0 flex items-center">
                            <div className="w-full border-t border-neutral-200 dark:border-neutral-800" />
                        </div>
                        <div className="relative flex justify-center text-xs uppercase tracking-widest text-neutral-400">
                            <span className="px-4 bg-transparent backdrop-blur-md">ou acesso rápido</span>
                        </div>
                    </div>

                    <div className="mt-8 grid gap-4">
                        <Button
                            variant="secondary"
                            onClick={login}
                            className="w-full"
                            icon={
                                <div className="w-6 h-6 bg-white dark:bg-neutral-900 rounded-lg flex items-center justify-center">
                                    <ShoppingBag className="w-3.5 h-3.5 text-neutral-900 dark:text-neutral-50" />
                                </div>
                            }
                        >
                            Conectar Mercado Livre
                            <ArrowRight className="w-4 h-4 ml-1 opacity-50" />
                        </Button>

                        <Link to="/register" virtual-link="true" className="w-full">
                            <Button variant="ghost" className="w-full" icon={<UserPlus className="w-5 h-5" />}>
                                Criar Conta Gratuita
                            </Button>
                        </Link>
                    </div>
                </div>

                <footer className="mt-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    <p>© 2026 Meli360. Todos os direitos reservados.</p>
                </footer>
            </main>
        </div>
    )
}
