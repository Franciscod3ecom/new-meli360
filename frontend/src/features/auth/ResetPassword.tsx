import { useState } from 'react'
import { Link, useSearchParams, useNavigate } from 'react-router-dom'
import { api } from '../../services/api'
import { toast } from 'sonner'
import { Lock, Eye, EyeOff, CheckCircle, LayoutDashboard, ArrowLeft } from 'lucide-react'
import Button from '../../components/ui/Button'

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

    const renderContent = () => {
        if (!token) {
            return (
                <div className="text-center space-y-6">
                    <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto text-red-500 dark:text-red-400">
                        <Lock className="w-8 h-8" />
                    </div>
                    <div>
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-50 mb-2">Token Inválido</h3>
                        <p className="text-neutral-600 dark:text-neutral-400 text-sm">
                            O link de recuperação está incompleto ou expirado.
                        </p>
                    </div>
                    <Link to="/forgot-password" virtual-link="true">
                        <Button className="w-full" icon={<ArrowLeft className="w-5 h-5" />}>
                            Solicitar Novo Link
                        </Button>
                    </Link>
                </div>
            )
        }

        if (isSuccess) {
            return (
                <div className="text-center space-y-6">
                    <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto text-green-600 dark:text-green-400">
                        <CheckCircle className="w-8 h-8" />
                    </div>
                    <div>
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-50 mb-2">Senha Atualizada!</h3>
                        <p className="text-neutral-600 dark:text-neutral-400 text-sm">
                            Sua nova senha foi salva com sucesso. Você será redirecionado para o login em instantes.
                        </p>
                    </div>
                    <Link to="/login" virtual-link="true">
                        <Button className="w-full">
                            Fazer Login Agora
                        </Button>
                    </Link>
                </div>
            )
        }

        return (
            <form onSubmit={handleSubmit} className="space-y-5">
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 ml-1">
                        Nova Senha
                    </label>
                    <div className="relative">
                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
                        <input
                            type={showPassword ? 'text' : 'password'}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Mínimo 8 caracteres"
                            className="w-full pl-12 pr-12 py-3 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-neutral-50 placeholder:text-neutral-400 focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 focus:ring-1 focus:ring-brand-500 transition-all"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-4 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                        >
                            {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                        </button>
                    </div>
                </div>

                <div className="space-y-2">
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 ml-1">
                        Confirmar Senha
                    </label>
                    <div className="relative">
                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
                        <input
                            type={showPassword ? 'text' : 'password'}
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            placeholder="Repita a nova senha"
                            className="w-full pl-12 pr-4 py-3 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-neutral-50 placeholder:text-neutral-400 focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 focus:ring-1 focus:ring-brand-500 transition-all"
                            required
                        />
                    </div>
                </div>

                <Button
                    type="submit"
                    isLoading={isLoading}
                    className="w-full mt-4"
                    icon={<Lock className="w-5 h-5" />}
                >
                    Salvar Nova Senha
                </Button>
            </form>
        )
    }

    return (
        <div className="min-h-screen w-full bg-neutral-0 dark:bg-neutral-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden transition-colors">
            {/* Background */}
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
                        {isSuccess ? 'Tudo Pronto!' : 'Nova Senha'}
                    </h2>
                    <p className="text-lg text-neutral-600 dark:text-neutral-400">
                        {isSuccess ? 'Sua senha foi redefinida.' : 'Escolha uma senha forte desta vez!'}
                    </p>
                </div>

                <div className="glass rounded-3xl p-8 sm:p-10 shadow-2xl">
                    {renderContent()}
                </div>

                <footer className="mt-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    <p>&copy; 2026 Meli360. Todos os direitos reservados.</p>
                </footer>
            </main>
        </div>
    )
}
