/**
 * API 封装模块
 * 与后端 PHP API 对接，数据与 iOS App 完全打通
 */

const API_BASE_URL = localStorage.getItem('api_base_url') || '';
const API_KEY = localStorage.getItem('api_key') || '';

class API {
    static async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}/api${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'X-API-Key': API_KEY,
            ...options.headers
        };

        const token = localStorage.getItem('auth_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            if (!response.ok) {
                const error = new Error(data.error || data.message || `HTTP ${response.status}`);
                Object.assign(error, data);
                throw error;
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // ========== 认证相关 ==========
    static async register(deviceId) {
        return this.request('/auth/register', {
            method: 'POST',
            body: JSON.stringify({ device_id: deviceId })
        });
    }

    static async login(deviceId) {
        return this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ device_id: deviceId })
        });
    }

    static async passwordLogin(login, password) {
        return this.request('/auth/password-login', {
            method: 'POST',
            body: JSON.stringify({ login, password })
        });
    }

    static async refreshToken(userId) {
        return this.request('/auth/refresh', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId })
        });
    }

    // ========== 用户相关 ==========
    static async getUserProfile(userId) {
        return this.request(`/user/profile?user_id=${userId}`);
    }

    static async getUserBalance(userId) {
        return this.request(`/users/${userId}/balance`);
    }

    static async getUserMembership(userId) {
        return this.request(`/user/membership?user_id=${userId}`);
    }

    static async getAccountInfo(userId) {
        return this.request(`/auth/account-info?user_id=${userId}`);
    }

    static async bindEmail(userId, email) {
        return this.request('/auth/bind-email', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, email })
        });
    }

    static async changePassword(userId, oldPassword, newPassword) {
        return this.request('/auth/change-password', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, old_password: oldPassword, new_password: newPassword })
        });
    }

    // ========== 服务相关 ==========
    static async getServices() {
        return this.request('/services');
    }

    static async getCountries(serviceId, userId = '') {
        const userParam = userId ? `&user_id=${userId}` : '';
        return this.request(`/countries?service_id=${serviceId}${userParam}`);
    }

    static async getServicePrice(serviceId, countryId) {
        return this.request(`/services/price?service_id=${serviceId}&country_id=${countryId}`);
    }

    static async getCalculatedPrice(serviceId, countryId, userId) {
        return this.request(`/services/price/calculated?service_id=${serviceId}&country_id=${countryId}&user_id=${userId}`);
    }

    // ========== 订单相关 ==========
    static async createOrder(userId, serviceId, countryId, pricePoints) {
        return this.request('/orders', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, service_id: serviceId, country_id: countryId, price_points: pricePoints })
        });
    }

    static async createBatchOrders(userId, serviceId, countryId, quantity, pricePoints) {
        return this.request('/orders/batch', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, service_id: serviceId, country_id: countryId, quantity, price_points: pricePoints })
        });
    }

    static async activateOrder(orderId) {
        return this.request(`/orders/${orderId}/activate`, {
            method: 'POST'
        });
    }

    static async cancelOrder(orderId) {
        return this.request(`/orders/${orderId}/cancel`, {
            method: 'POST'
        });
    }

    static async getOrders(userId, limit = 50, offset = 0) {
        return this.request(`/orders?user_id=${userId}&limit=${limit}&offset=${offset}`);
    }

    static async getOrderSms(orderId) {
        return this.request(`/orders/${orderId}/sms`);
    }

    static async getOrderDetail(orderId) {
        return this.request(`/orders/${orderId}`);
    }

    // ========== 收藏相关 ==========
    static async getFavorites(userId) {
        return this.request(`/favorites?user_id=${userId}`);
    }

    static async addFavorite(userId, serviceId, countryId, name) {
        return this.request('/favorites', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, service_id: serviceId, country_id: countryId, name })
        });
    }

    static async removeFavorite(favoriteId) {
        return this.request(`/favorites/${favoriteId}`, {
            method: 'DELETE'
        });
    }

    // ========== 通知相关 ==========
    static async getNotifications(userId, limit = 20, offset = 0) {
        return this.request(`/notifications?user_id=${userId}&limit=${limit}&offset=${offset}`);
    }

    static async markNotificationRead(notificationId) {
        return this.request(`/notifications/${notificationId}/read`, {
            method: 'POST'
        });
    }

    static async markAllNotificationsRead(userId) {
        return this.request('/notifications/read-all', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId })
        });
    }

    // ========== 充值相关 ==========
    static async getPaymentConfigs() {
        return this.request('/payment-configs');
    }

    static async getPointsPackages() {
        return this.request('/points/packages');
    }

    // ========== 系统相关 ==========
    static async getSettings() {
        return this.request('/settings');
    }

    static async getApiKey() {
        return this.request('/api-key');
    }

    static async getStats() {
        return this.request('/stats');
    }
}
