import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../services/api'
import { toast } from 'sonner'
import { Mail, ArrowLeft, LayoutDashboard } from 'lucide-react'
import Button from '../../components/ui/Button'

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
                        {isSent ? 'Verifique seu e-mail' : 'Recuperar Senha'}
                    </h2>
                    <p className="text-lg text-neutral-600 dark:text-neutral-400">
                        {isSent ? 'Um link de recuperação foi enviado.' : 'Ops! Acontece com os melhores.'}
                    </p>
                </div>

                <div className="glass rounded-3xl p-8 sm:p-10 shadow-2xl">
                    {isSent ? (
                        <div className="text-center space-y-6">
                            <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto text-green-600 dark:text-green-400">
                                <Mail className="w-8 h-8" />
                            </div>
                            <p className="text-neutral-600 dark:text-neutral-400 text-sm leading-relaxed">
                                Se o e-mail <strong className="text-neutral-900 dark:text-neutral-100">{email}</strong> estiver cadastrado, você receberá um link para redefinir sua senha em instantes.
                            </p>
                            <Link to="/login" virtual-link="true">
                                <Button variant="ghost" className="w-full mt-2" icon={<ArrowLeft className="w-5 h-5" />}>
                                    Voltar para o Login
                                </Button>
                            </Link>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="space-y-2">
                                <label htmlFor="email" className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 ml-1">
                                    E-mail da sua conta
                                </label>
                                <div className="relative">
                                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
                                    <input
                                        id="email"
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="w-full pl-12 pr-4 py-3 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-neutral-50 placeholder:text-neutral-400 focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 focus:ring-1 focus:ring-brand-500 transition-all"
                                        placeholder="exemplo@email.com"
                                        required
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                isLoading={isLoading}
                                className="w-full mt-4"
                                icon={<Mail className="w-5 h-5" />}
                            >
                                Enviar Link de Recuperação
                            </Button>

                            <div className="text-center pt-2">
                                <Link
                                    to="/login"
                                    virtual-link="true"
                                    className="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                    Voltar para o Login
                                </Link>
                            </div>
                        </form>
                    )}
                </div>

                <footer className="mt-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    <p>&copy; 2026 Meli360. Todos os direitos reservados.</p>
                </footer>
            </main>
        </div>
    )
}
