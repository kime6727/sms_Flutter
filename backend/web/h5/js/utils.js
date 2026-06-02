/**
 * 工具函数模块
 */

const Utils = {
    // 生成唯一设备ID
    generateDeviceId() {
        let deviceId = localStorage.getItem('device_id');
        if (!deviceId) {
            deviceId = 'web_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('device_id', deviceId);
        }
        return deviceId;
    },

    // 获取当前用户
    getCurrentUser() {
        const user = localStorage.getItem('current_user');
        return user ? JSON.parse(user) : null;
    },

    // 设置当前用户
    setCurrentUser(user) {
        localStorage.setItem('current_user', JSON.stringify(user));
    },

    // 清除登录状态
    clearAuth() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('current_user');
        localStorage.removeItem('device_id');
    },

    // 格式化价格（积分转美元显示）
    formatPrice(points) {
        if (!points && points !== 0) return '-';
        return '$' + (points / 100).toFixed(2);
    },

    // 格式化积分
    formatPoints(points) {
        if (!points && points !== 0) return '0';
        return points.toLocaleString();
    },

    // 格式化日期
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleString('zh-CN', {
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    // 格式化倒计时
    formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    },

    // 获取订单状态文本和颜色
    getOrderStatus(status) {
        const map = {
            'pending': { text: '未使用', color: '#f59e0b', bg: '#fffbeb' },
            'active': { text: '等待短信', color: '#3b82f6', bg: '#eff6ff' },
            'completed': { text: '已完成', color: '#10b981', bg: '#ecfdf5' },
            'cancelled': { text: '已取消', color: '#6b7280', bg: '#f3f4f6' },
            'expired': { text: '已过期', color: '#ef4444', bg: '#fef2f2' },
            'refunded': { text: '已退款', color: '#8b5cf6', bg: '#f5f3ff' }
        };
        return map[status] || { text: status, color: '#6b7280', bg: '#f3f4f6' };
    },

    // 复制到剪贴板
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // 降级方案
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            const result = document.execCommand('copy');
            document.body.removeChild(textarea);
            return result;
        }
    },

    // 防抖
    debounce(fn, delay = 300) {
        let timer = null;
        return function (...args) {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    },

    // 显示 Toast 提示
    toast(message, type = 'success') {
        const existing = document.querySelector('.toast-message');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // 触发重绘
        toast.offsetHeight;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    },

    // 显示加载中
    showLoading(text = '加载中...') {
        let loading = document.querySelector('.global-loading');
        if (!loading) {
            loading = document.createElement('div');
            loading.className = 'global-loading';
            loading.innerHTML = `
                <div class="loading-spinner"></div>
                <div class="loading-text">${text}</div>
            `;
            document.body.appendChild(loading);
        }
        loading.style.display = 'flex';
    },

    // 隐藏加载中
    hideLoading() {
        const loading = document.querySelector('.global-loading');
        if (loading) loading.style.display = 'none';
    },

    // 确认对话框
    confirm(title, message) {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-title">${title}</div>
                    <div class="confirm-message">${message}</div>
                    <div class="confirm-buttons">
                        <button class="btn-cancel">取消</button>
                        <button class="btn-confirm">确认</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            modal.querySelector('.btn-cancel').addEventListener('click', () => {
                modal.remove();
                resolve(false);
            });
            modal.querySelector('.btn-confirm').addEventListener('click', () => {
                modal.remove();
                resolve(true);
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(false);
                }
            });
        });
    },

    // 从短信提取验证码
    extractCode(sms) {
        if (!sms) return null;
        const matches = sms.match(/\b\d{4,8}\b/);
        return matches ? matches[0] : null;
    },

    // 服务图标映射
    getServiceIcon(code) {
        const icons = {
            'tg': '✈️', 'wa': '💬', 'go': '🔍', 'fb': '👥',
            'ig': '📷', 'tw': '🐦', 'yt': '▶️', 'tt': '🎵',
            'am': '🛒', 'ap': '🍎', 'ms': 'Ⓜ️', 'nf': '🎬',
            'sp': '🎧', 'pp': '💳', 'ds': '🎮', 'li': '💼'
        };
        return icons[code?.toLowerCase()] || '📱';
    }
};
