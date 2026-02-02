import { ReactNode } from 'react'
import { useAuth } from '../../../context/AuthContext'
import { LayoutDashboard, Link2, ShieldCheck, ArrowRight } from 'lucide-react'

interface OnboardingGateProps {
    children: ReactNode
}

export function OnboardingGate({ children }: OnboardingGateProps) {
    const { user, login } = useAuth()

    if (!user?.needs_account_link) {
        return <>{children}</>
    }

    return (
        <div className="min-h-[80vh] flex items-center justify-center p-4">
            <div className="max-w-2xl w-full bg-white rounded-3xl shadow-xl shadow-blue-100/50 border border-blue-50 overflow-hidden flex flex-col md:flex-row">
                {/* Left side: Visual/Iconography */}
                <div className="md:w-1/3 bg-gradient-to-br from-blue-600 to-blue-800 p-8 flex flex-col items-center justify-center text-white text-center">
                    <div className="w-20 h-20 bg-white/10 rounded-2xl backdrop-blur-sm flex items-center justify-center mb-4 border border-white/20">
                        <Link2 className="w-10 h-10" />
                    </div>
                    <h3 className="text-xl font-bold">Quase lá!</h3>
                    <p className="text-blue-100 text-sm mt-2 opacity-80">
                        Sua conta Meli360 está pronta.
                    </p>
                </div>

                {/* Right side: Content/Action */}
                <div className="md:w-2/3 p-8 md:p-12">
                    <div className="space-y-6">
                        <div>
                            <h2 className="text-2xl font-extrabold text-gray-900">
                                Conecte sua primeira conta
                            </h2>
                            <p className="text-gray-500 mt-2">
                                Para começar a analisar seus anúncios e custos de frete, precisamos sincronizar seus dados do Mercado Livre.
                            </p>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-start gap-3">
                                <div className="mt-1 bg-green-50 p-1 rounded-full">
                                    <ShieldCheck className="w-4 h-4 text-green-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-bold text-gray-700">Seguro e Oficial</p>
                                    <p className="text-xs text-gray-500">Usamos a API oficial do Mercado Livre para garantir sua segurança.</p>
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <div className="mt-1 bg-blue-50 p-1 rounded-full">
                                    <LayoutDashboard className="w-4 h-4 text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-bold text-gray-700">Painel Completo</p>
                                    <p className="text-xs text-gray-500">Após conectar, você terá acesso total ao inventário e análise de fretes.</p>
                                </div>
                            </div>
                        </div>

                        <button
                            onClick={login}
                            className="w-full flex items-center justify-center gap-2 py-4 px-6 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-lg shadow-lg shadow-blue-200 transition-all active:scale-95 group"
                        >
                            Conectar Conta agora
                            <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                        </button>

                        <p className="text-center text-xs text-gray-400">
                            Ao conectar, você autoriza o Meli360 a ler dados de seus anúncios para gerar as análises.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    )
}
