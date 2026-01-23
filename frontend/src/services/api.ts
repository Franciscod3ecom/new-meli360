
const BACKEND_URL = import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000';

export const api = {
    triggerSync: async () => {
        try {
            const response = await fetch(`${BACKEND_URL}/cron/sync.php`);
            if (!response.ok) throw new Error('Sync failed');
            return true;
        } catch (error) {
            console.error('Sync error:', error);
            return false;
        }
    },
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
    exportCSV: () => {
        window.location.href = `${BACKEND_URL}/api/export_csv.php`;
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
                body: JSON.stringify({ target_user_id: targetUserId })
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

  async getAnalytics() {
    const res = await fetch(`${BACKEND_URL}/api/analytics.php`, {
      credentials: 'include'
    })
    if (!res.ok) throw new Error('Failed to fetch analytics')
    return res.json()
  }
};
