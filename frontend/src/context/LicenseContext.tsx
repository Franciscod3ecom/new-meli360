import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react'
import { api } from '../services/api'
import { toast } from 'sonner'

interface LicenseContextType {
    isLicenseValid: boolean
    isCheckingLicense: boolean
    validateLicense: (email: string, mlUserId: string) => Promise<boolean>
    checkLicense: () => Promise<void>
    resetLicense: () => void
}

const LicenseContext = createContext<LicenseContextType | undefined>(undefined)

export function LicenseProvider({ children }: { children: ReactNode }) {
    const [isLicenseValid, setIsLicenseValid] = useState(false)
    const [isCheckingLicense, setIsCheckingLicense] = useState(true)

    const checkLicense = useCallback(async () => {
        try {
            const result = await api.checkLicense()
            setIsLicenseValid(result.validated === true)
        } catch (error) {
            console.error('License check failed:', error)
            setIsLicenseValid(false)
        } finally {
            setIsCheckingLicense(false)
        }
    }, [])

    useEffect(() => {
        checkLicense()
    }, [checkLicense])

    const validateLicense = async (email: string, mlUserId: string): Promise<boolean> => {
        try {
            const result = await api.validateLicense(email, mlUserId)

            if (result.autorizado === true) {
                setIsLicenseValid(true)
                toast.success('Licença ativada com sucesso!')
                return true
            } else {
                toast.error(result.mensagem || 'Email não autorizado')
                return false
            }
        } catch (error: any) {
            toast.error(error.message || 'Erro ao validar licença')
            return false
        }
    }

    const resetLicense = () => {
        setIsLicenseValid(false)
        window.location.reload()
    }

    return (
        <LicenseContext.Provider value={{ isLicenseValid, isCheckingLicense, validateLicense, checkLicense, resetLicense }}>
            {children}
        </LicenseContext.Provider>
    )
}

export function useLicense() {
    const context = useContext(LicenseContext)
    if (context === undefined) {
        throw new Error('useLicense must be used within a LicenseProvider')
    }
    return context
}
