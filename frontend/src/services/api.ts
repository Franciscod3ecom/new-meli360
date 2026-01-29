
const BACKEND_URL = import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000';

export const api = {

    getAuthUrl: () => {
        // Redirects to the PHP login script which handles the OAuth redirection
        window.location.href = `${BACKEND_URL}/auth/login.php`;
    },
    bulkUpdate: async (itemIds: string[], action: 'paused' | 'active') => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/bulk_update.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ item_ids: itemIds, action })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Bulk update failed');
            return data;
        } catch (error) {
            console.error('Bulk update error:', error);
            throw error;
        }
    },
    bulkPause: async (itemIds: string[]) => {
        try {
            const formData = new FormData();
            itemIds.forEach(id => formData.append('item_ids[]', id));

            const response = await fetch(`${BACKEND_URL}/api/bulk_pause.php`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Bulk pause failed');
            return data;
        } catch (error) {
            console.error('Bulk pause error:', error);
            throw error;
        }
    },

    getItems: async (params: {
        page?: number;
        limit?: number;
        status_filter?: string;
        sales_filter?: string;
    } = {}) => {
        try {
            const queryParams = new URLSearchParams();
            if (params.page) queryParams.append('page', params.page.toString());
            if (params.limit) queryParams.append('limit', params.limit.toString());
            if (params.status_filter) queryParams.append('status_filter', params.status_filter);
            if (params.sales_filter) queryParams.append('sales_filter', params.sales_filter);

            const response = await fetch(`${BACKEND_URL}/api/get_items.php?${queryParams}`);
            if (!response.ok) throw new Error('Failed to fetch items');
            return await response.json();
        } catch (error) {
            console.error('Get items error:', error);
            throw error;
        }
    },
    checkAuth: async () => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/me.php`);
            if (!response.ok) return { authenticated: false };
            return await response.json();
        } catch (error) {
            console.error('Auth check error:', error);
            return { authenticated: false };
        }
    },
    getAccounts: async () => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/accounts.php`);
            if (!response.ok) throw new Error('Failed to fetch accounts');
            return await response.json();
        } catch (error) {
            console.error('Get accounts error:', error);
            throw error;
        }
    },
    switchAccount: async (targetUserId: string) => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/switch_account.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ account_id: targetUserId })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Switch account failed');
            return data;
        } catch (error) {
            console.error('Switch account error:', error);
            throw error;
        }
    },
    validateLicense: async (email: string, mlUserId: string) => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/validate_license.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, ml_user_id: mlUserId })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Validation failed');
            return data;
        } catch (error) {
            console.error('License validation error:', error);
            throw error;
        }
    },
    checkLicense: async () => {
        try {
            const response = await fetch(`${BACKEND_URL}/api/check_license.php`);
            if (!response.ok) return { validated: false };
            return await response.json();
        } catch (error) {
            console.error('License check error:', error);
            return { validated: false };
        }
    },

    getAnalytics: async () => {
        const res = await fetch(`${BACKEND_URL}/api/analytics.php`, {
            credentials: 'include'
        })
        if (!res.ok) throw new Error('Failed to fetch analytics')
        return res.json()
    },
    register: async (email: string, password: string, confirmPassword: string) => {
        try {
            const response = await fetch(`${BACKEND_URL}/auth/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, confirm_password: confirmPassword })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Registration failed');
            return data;
        } catch (error) {
            console.error('Registration error:', error);
            throw error;
        }
    },
    loginNative: async (email: string, password: string) => {
        try {
            const response = await fetch(`${BACKEND_URL}/auth/login_native.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Login failed');
            return data;
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    },
    triggerSync: async () => {
        try {
            let offset = 0;
            let completed = false;
            let totalProcessed = 0;
            let totalItems = 0;

            // Loop até processar todos os itens
            while (!completed) {
                const response = await fetch(`${BACKEND_URL}/api/sync.php?offset=${offset}`, {
                    method: 'GET',
                    credentials: 'include'
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Sync failed');
                }

                const data = await response.json();

                completed = data.completed;
                totalProcessed = data.processed || 0;
                totalItems = data.total || 0;
                offset = totalProcessed;

                // Log progresso
                console.log(`Sync progress: ${totalProcessed}/${totalItems}`);
            }

            return {
                success: true,
                processed: totalProcessed,
                total: totalItems,
                message: `Sincronizados ${totalProcessed} itens com sucesso!`
            };
        } catch (error) {
            console.error('Sync error:', error);
            throw error;
        }
    },
    exportCSV: () => {
        // Redireciona o navegador para download
        window.open(`${BACKEND_URL}/api/export_csv.php`, '_blank');
    }
};
