import { useState } from 'react'
import { useLicense } from '../../../context/LicenseContext'
import { ShieldCheck, Mail, Loader2 } from 'lucide-react'

export default function LicenseActivation() {
    const [email, setEmail] = useState('')
    const [isValidating, setIsValidating] = useState(false)
    const { validateLicense } = useLicense()

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()

        if (!email || !email.includes('@')) {
            alert('Por favor, digite um email válido')
            return
        }

        // Use temporary ID - will be updated after ML login
        // The license validation will be re-checked with real ML ID after login
        const tempMlUserId = 'PENDING_ML_LOGIN'

        setIsValidating(true)
        try {
            await validateLicense(email.trim().toLowerCase(), tempMlUserId)
        } finally {
            setIsValidating(false)
        }
    }

    return (
        <div className="fixed inset-0 min-h-screen w-full bg-gradient-to-br from-gray-900 via-black to-gray-900 flex items-center justify-center p-6 overflow-y-auto">
            <div className="max-w-md w-full my-auto">
                {/* Card */}
                <div className="bg-white/10 backdrop-blur-lg rounded-2xl shadow-2xl border border-white/20 p-8">
                    {/* Icon */}
                    <div className="flex justify-center mb-6">
                        <div className="p-4 bg-yellow-500/20 rounded-full">
                            <ShieldCheck className="w-12 h-12 text-yellow-400" />
                        </div>
                    </div>

                    {/* Title */}
                    <h1 className="text-3xl font-bold text-white text-center mb-2">
                        Ativação do Sistema
                    </h1>
                    <p className="text-gray-300 text-center mb-8">
                        Digite o email utilizado na compra do Meli360
                    </p>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Email Input */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Email de Compra
                            </label>
                            <div className="relative">
                                <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="seu@email.com"
                                    disabled={isValidating}
                                    className="w-full pl-11 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                    required
                                />
                            </div>
                        </div>

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={isValidating}
                            className="w-full py-3 px-6 bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-500 hover:to-yellow-400 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2"
                        >
                            {isValidating ? (
                                <>
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                    Validando...
                                </>
                            ) : (
                                'Ativar Licença'
                            )}
                        </button>
                    </form>

                    {/* Help Text */}
                    <p className="text-xs text-gray-400 text-center mt-6">
                        O email será validado no servidor. Entre em contato com o suporte se tiver problemas.
                    </p>
                </div>

                {/* Footer */}
                <p className="text-center text-gray-500 text-sm mt-6">
                    Meli360 © 2026 - Sistema de Análise de Anúncios
                </p>
            </div>
        </div>
    )
}
