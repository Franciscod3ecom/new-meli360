
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
    }
};
