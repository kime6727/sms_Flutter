/**
 * 云码 H5 主应用 - SPA 单页应用
 * 页面路由和核心逻辑
 */

class App {
    constructor() {
        this.currentPage = '';
        this.currentUser = null;
        this.services = [];
        this.countries = [];
        this.orders = [];
        this.favorites = [];
        this.selectedService = null;
        this.selectedCountry = null;
        this.pollingTimers = {};
        this.init();
    }

    async init() {
        // 检查是否已配置 API
        const apiUrl = localStorage.getItem('api_base_url');
        if (!apiUrl) {
            this.navigate('config');
            return;
        }

        // 尝试恢复登录状态
        const user = Utils.getCurrentUser();
        if (user) {
            this.currentUser = user;
            try {
                const res = await API.getUserProfile(user.id);
                if (res.success) {
                    this.currentUser = { ...user, ...res.data };
                    Utils.setCurrentUser(this.currentUser);
                    this.navigate('home');
                } else {
                    // Token 失效，清除登录状态
                    Utils.clearAuth();
                    this.currentUser = null;
                    this.navigate('login');
                }
            } catch (e) {
                console.log('Token expired, need re-login');
                Utils.clearAuth();
                this.currentUser = null;
                this.navigate('login');
            }
        } else {
            // 未登录，直接跳转到登录页
            this.navigate('login');
        }

        this.bindEvents();
    }

    // ===== 页面导航 =====
    navigate(page, data = null) {
        // 隐藏所有页面
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));

        // 显示目标页面
        const target = document.getElementById(`page-${page}`);
        if (target) {
            target.classList.add('active');
            this.currentPage = page;

            // 更新底部导航
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            const navMap = { home: 0, orders: 1, profile: 2 };
            if (navMap[page] !== undefined) {
                document.querySelectorAll('.nav-item')[navMap[page]]?.classList.add('active');
            }

            // 页面初始化
            this.initPage(page, data);
        }

        window.scrollTo(0, 0);
    }

    async initPage(page, data) {
        switch (page) {
            case 'home':
                await this.loadServices();
                break;
            case 'countries':
                this.selectedService = data;
                await this.loadCountries(data.id);
                break;
            case 'buy':
                this.selectedCountry = data;
                this.renderBuyPage();
                break;
            case 'orders':
                await this.loadOrders();
                break;
            case 'order-detail':
                await this.loadOrderDetail(data);
                break;
            case 'profile':
                await this.loadProfile();
                break;
            case 'recharge':
                await this.loadRecharge();
                break;
            case 'favorites':
                await this.loadFavorites();
                break;
            case 'notifications':
                await this.loadNotifications();
                break;
            case 'settings':
                this.renderSettings();
                break;
            case 'login':
                this.renderLogin();
                break;
            case 'config':
                this.renderConfig();
                break;
        }
    }

    // ===== 事件绑定 =====
    bindEvents() {
        // 底部导航
        document.querySelectorAll('.nav-item').forEach((item, idx) => {
            item.addEventListener('click', () => {
                const pages = ['home', 'orders', 'profile'];
                if (!this.currentUser && (idx === 1 || idx === 2)) {
                    this.navigate('login');
                    return;
                }
                this.navigate(pages[idx]);
            });
        });
    }

    // ===== 配置页面 =====
    renderConfig() {
        const page = document.getElementById('page-config');
        page.innerHTML = `
            <div class="config-page">
                <h2>欢迎使用云码</h2>
                <p>请配置您的服务器地址以开始使用</p>
                <div class="config-form">
                    <div class="form-group">
                        <label class="form-label">服务器地址</label>
                        <input type="text" class="form-input" id="config-url" placeholder="https://your-domain.com">
                    </div>
                    <button class="btn btn-primary btn-block" onclick="app.saveConfig()">保存并进入</button>
                </div>
            </div>
        `;
    }

    async saveConfig() {
        const url = document.getElementById('config-url').value.trim();
        if (!url) {
            Utils.toast('请输入服务器地址', 'error');
            return;
        }
        localStorage.setItem('api_base_url', url.replace(/\/$/, ''));

        try {
            const res = await API.getApiKey();
            if (res.api_key) {
                localStorage.setItem('api_key', res.api_key);
                Utils.toast('配置成功');
                location.reload();
            }
        } catch (e) {
            Utils.toast('无法连接到服务器', 'error');
        }
    }

    // ===== 登录页面 =====
    renderLogin() {
        const page = document.getElementById('page-login');
        page.innerHTML = `
            <div class="login-page">
                <div class="login-header">
                    <div class="logo-icon">☁️</div>
                    <h1>云码接码</h1>
                    <p>全球虚拟号码接码平台</p>
                </div>
                <div class="login-form">
                    <div class="login-tabs">
                        <button class="login-tab active" onclick="app.switchLoginTab('auto')">一键登录</button>
                        <button class="login-tab" onclick="app.switchLoginTab('password')">账号登录</button>
                    </div>
                    <div id="login-auto">
                        <p style="color:var(--text-secondary);margin-bottom:20px;font-size:0.9rem;">点击下方按钮，系统将自动为您创建账号</p>
                        <button class="btn btn-primary btn-block login-btn" onclick="app.autoLogin()">一键登录 / 注册</button>
                    </div>
                    <div id="login-password" style="display:none;">
                        <div class="form-group">
                            <input type="text" class="form-input" id="login-username" placeholder="账号">
                        </div>
                        <div class="form-group">
                            <input type="password" class="form-input" id="login-password-input" placeholder="密码">
                        </div>
                        <button class="btn btn-primary btn-block login-btn" onclick="app.passwordLogin()">登录</button>
                    </div>
                    <p class="login-hint">登录即表示您同意服务条款和隐私政策</p>
                </div>
            </div>
        `;
    }

    switchLoginTab(tab) {
        document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('login-auto').style.display = tab === 'auto' ? 'block' : 'none';
        document.getElementById('login-password').style.display = tab === 'password' ? 'block' : 'none';
    }

    async autoLogin() {
        Utils.showLoading('登录中...');
        try {
            const deviceId = Utils.generateDeviceId();
            const res = await API.login(deviceId);
            if (res.success) {
                localStorage.setItem('auth_token', res.token);
                this.currentUser = res.user;
                Utils.setCurrentUser(res.user);
                Utils.toast('登录成功');
                this.navigate('home');
            }
        } catch (e) {
            Utils.toast(e.message || '登录失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    async passwordLogin() {
        const login = document.getElementById('login-username').value.trim();
        const password = document.getElementById('login-password-input').value;
        if (!login || !password) {
            Utils.toast('请输入账号和密码', 'error');
            return;
        }
        Utils.showLoading('登录中...');
        try {
            const res = await API.passwordLogin(login, password);
            if (res.success) {
                localStorage.setItem('auth_token', res.token);
                this.currentUser = res.user;
                Utils.setCurrentUser(res.user);
                Utils.toast('登录成功');
                this.navigate('home');
            }
        } catch (e) {
            Utils.toast(e.message || '登录失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    // ===== 首页 - 服务列表 =====
    async loadServices() {
        Utils.showLoading();
        try {
            const res = await API.getServices();
            this.services = res.data || [];
            this.renderServices();
        } catch (e) {
            Utils.toast('加载服务失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderServices() {
        const container = document.getElementById('services-grid');
        if (!container) return;

        if (!this.services.length) {
            container.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><p>暂无可用服务</p></div>';
            return;
        }

        container.innerHTML = this.services.map(s => `
            <div class="service-item" onclick="app.navigate('countries', ${JSON.stringify(s).replace(/"/g, '&quot;')})">
                <div class="service-icon">${Utils.getServiceIcon(s.code)}</div>
                <div class="service-name">${s.name_cn || s.name}</div>
            </div>
        `).join('');
    }

    // ===== 国家列表 =====
    async loadCountries(serviceId) {
        Utils.showLoading();
        try {
            const res = await API.getCountries(serviceId, this.currentUser?.id || '');
            this.countries = res.data || [];
            this.renderCountries();
        } catch (e) {
            Utils.toast('加载国家列表失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderCountries() {
        const page = document.getElementById('page-countries');
        const service = this.selectedService;

        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('home')">←</button>
                <h1>选择国家 - ${service.name_cn || service.name}</h1>
            </div>
            <div class="country-list">
                ${this.countries.map(c => `
                    <div class="country-item" onclick="app.navigate('buy', ${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="country-flag">${c.flag || '🏳️'}</div>
                        <div class="country-info">
                            <div class="country-name">${c.name}</div>
                            <div class="country-code">+${c.phone_code || ''}</div>
                        </div>
                        <div class="country-price">${c.price || 0} 积分</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // ===== 购买页面 =====
    renderBuyPage() {
        const page = document.getElementById('page-buy');
        const service = this.selectedService;
        const country = this.selectedCountry;
        const user = this.currentUser;

        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('countries', ${JSON.stringify(service).replace(/"/g, '&quot;')})">←</button>
                <h1>确认购买</h1>
            </div>
            <div style="padding:16px;">
                <div class="card">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <div style="font-size:2rem;">${Utils.getServiceIcon(service.code)}</div>
                        <div>
                            <div style="font-weight:700;">${service.name_cn || service.name}</div>
                            <div style="font-size:0.85rem;color:var(--text-muted);">${country.flag || '🏳️'} ${country.name}</div>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:12px 0;border-top:1px solid var(--border);">
                        <span style="color:var(--text-secondary);">单价</span>
                        <span style="font-weight:700;">${country.price || 0} 积分</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">购买数量</div>
                    <div class="quantity-selector">
                        <button onclick="app.changeQuantity(-1)">−</button>
                        <span class="quantity-value" id="buy-quantity">1</span>
                        <button onclick="app.changeQuantity(1)">+</button>
                    </div>
                </div>

                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="color:var(--text-secondary);">我的积分</span>
                        <span style="font-weight:700;">${user ? Utils.formatPoints(user.balance) : '0'} 积分</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                        <span style="color:var(--text-secondary);">合计</span>
                        <span style="font-weight:800;font-size:1.1rem;color:var(--accent);" id="buy-total">${country.price || 0} 积分</span>
                    </div>
                </div>

                <button class="btn btn-primary btn-block" style="margin-top:16px;" onclick="app.createOrder()">立即购买</button>
                ${!user ? '<p style="text-align:center;margin-top:12px;color:var(--text-muted);font-size:0.85rem;">购买前请先登录</p>' : ''}
            </div>
        `;
    }

    changeQuantity(delta) {
        const el = document.getElementById('buy-quantity');
        let qty = parseInt(el.textContent) + delta;
        qty = Math.max(1, Math.min(10, qty));
        el.textContent = qty;

        const price = this.selectedCountry?.price || 0;
        document.getElementById('buy-total').textContent = (price * qty) + ' 积分';
    }

    async createOrder(confirmedPrice = null) {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }

        const quantity = parseInt(document.getElementById('buy-quantity').textContent);
        const price = confirmedPrice !== null ? confirmedPrice : (this.selectedCountry.price || 0);
        const total = price * quantity;

        if (this.currentUser.balance < total) {
            const ok = await Utils.confirm('积分不足', `需要 ${total} 积分，当前余额 ${this.currentUser.balance} 积分。是否前往充值？`);
            if (ok) this.navigate('recharge');
            return;
        }

        Utils.showLoading('创建订单中...');
        try {
            if (quantity === 1) {
                const res = await API.createOrder(this.currentUser.id, this.selectedService.id, this.selectedCountry.id, price);
                if (res.success) {
                    this.currentUser.balance = res.remaining_balance;
                    Utils.setCurrentUser(this.currentUser);
                    this.selectedCountry.price = price;
                    Utils.toast('购买成功');
                    this.navigate('orders');
                }
            } else {
                const res = await API.createBatchOrders(this.currentUser.id, this.selectedService.id, this.selectedCountry.id, quantity, price);
                if (res.success) {
                    this.currentUser.balance = res.remaining_balance;
                    Utils.setCurrentUser(this.currentUser);
                    this.selectedCountry.price = price;
                    Utils.toast(`成功购买 ${quantity} 个订单`);
                    this.navigate('orders');
                }
            }
        } catch (e) {
            if (e.code === 'price_changed' && e.price_points) {
                Utils.hideLoading();
                const newPrice = parseInt(e.price_points);
                const newTotal = newPrice * quantity;
                const ok = await Utils.confirm('价格已更新', `当前单价已从 ${price} 积分变为 ${newPrice} 积分，合计 ${newTotal} 积分。是否按新价格继续购买？`);
                if (ok) {
                    this.selectedCountry.price = newPrice;
                    const totalEl = document.getElementById('buy-total');
                    if (totalEl) totalEl.textContent = newTotal + ' 积分';
                    await this.createOrder(newPrice);
                }
                return;
            }
            if (e.code === 'out_of_stock') {
                Utils.toast('当前号码暂时售罄，请选择其他国家或稍后再试', 'error');
                return;
            }
            Utils.toast(e.message || '购买失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    // ===== 订单列表 =====
    async loadOrders() {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        Utils.showLoading();
        try {
            const res = await API.getOrders(this.currentUser.id);
            this.orders = res.data || [];
            this.renderOrders();
        } catch (e) {
            Utils.toast('加载订单失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderOrders() {
        const container = document.getElementById('orders-list');
        if (!container) return;

        if (!this.orders.length) {
            container.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><p>暂无订单</p><button class="btn btn-outline btn-sm" style="margin-top:12px;" onclick="app.navigate(\'home\')">去购买</button></div>';
            return;
        }

        container.innerHTML = this.orders.map(o => {
            const status = Utils.getOrderStatus(o.status);
            return `
                <div class="order-item" onclick="app.navigate('order-detail', '${o.id}')">
                    <div class="order-icon">${Utils.getServiceIcon(o.service_icon)}</div>
                    <div class="order-info">
                        <div class="order-title">${o.service_name || '未知服务'}</div>
                        <div class="order-meta">${o.country_name || ''} · ${Utils.formatDate(o.created_at)}</div>
                    </div>
                    <span class="order-status" style="background:${status.bg};color:${status.color};">${status.text}</span>
                </div>
            `;
        }).join('');
    }

    // ===== 订单详情 =====
    async loadOrderDetail(orderId) {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        Utils.showLoading();
        try {
            const [orderRes, smsRes] = await Promise.all([
                API.getOrderDetail(orderId),
                API.getOrderSms(orderId)
            ]);

            const order = orderRes.data;
            const smsList = smsRes.data || [];
            this.renderOrderDetail(order, smsList);

            // 如果是 active 状态，开始轮询
            if (order.status === 'active') {
                this.startPolling(orderId);
            }
        } catch (e) {
            Utils.toast('加载订单详情失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderOrderDetail(order, smsList) {
        const page = document.getElementById('page-order-detail');
        const status = Utils.getOrderStatus(order.status);
        const canActivate = order.status === 'pending';
        const isActive = order.status === 'active';
        const isCompleted = order.status === 'completed';

        let phoneHtml = '';
        if (order.phone_number) {
            phoneHtml = `
                <div class="phone-display">
                    <div class="phone-number">${order.phone_number}</div>
                    <div class="phone-actions">
                        <button class="btn-copy" onclick="app.copyPhone('${order.phone_number}')">📋 复制号码</button>
                    </div>
                </div>
            `;
        }

        let actionHtml = '';
        if (canActivate) {
            actionHtml = `<button class="btn btn-primary btn-block" style="margin:16px;" onclick="app.activateOrder('${order.id}')">🚀 激活号码</button>`;
        } else if (isActive) {
            actionHtml = `
                <div style="padding:16px;text-align:center;">
                    <div class="countdown" id="order-countdown">20:00</div>
                    <p style="margin-top:8px;color:var(--text-muted);font-size:0.85rem;">请在有效期内使用此号码接收验证码</p>
                </div>
            `;
        }

        let smsHtml = '';
        if (smsList.length > 0) {
            smsHtml = `
                <div style="padding:16px;font-weight:700;">收到的短信</div>
                <div class="sms-list">
                    ${smsList.map(sms => {
                        const code = Utils.extractCode(sms.content);
                        return `
                            <div class="sms-item">
                                <div class="sms-sender">${sms.sender || 'Unknown'}</div>
                                <div class="sms-content">${sms.content}</div>
                                ${code ? `<div class="sms-code">${code}</div>` : ''}
                                <div class="sms-time">${Utils.formatDate(sms.received_at)}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        } else if (isActive) {
            smsHtml = `
                <div class="empty-state">
                    <div class="empty-icon">⏳</div>
                    <p>等待短信到达...<br><span style="font-size:0.8rem;">系统每 5 秒自动刷新</span></p>
                </div>
            `;
        }

        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('orders')">←</button>
                <h1>订单详情</h1>
                ${order.status === 'active' ? `<button class="header-action" onclick="app.cancelOrder('${order.id}')">取消</button>` : ''}
            </div>
            <div style="padding:16px;">
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <span style="font-weight:700;">${order.service_name || '未知服务'}</span>
                        <span class="order-status" style="background:${status.bg};color:${status.color};">${status.text}</span>
                    </div>
                    <div style="font-size:0.9rem;color:var(--text-secondary);">
                        <div>国家: ${order.country_name || '-'}</div>
                        <div>价格: ${order.total_price || 0} 积分</div>
                        <div>创建: ${Utils.formatDate(order.created_at)}</div>
                    </div>
                </div>
                ${phoneHtml}
                ${actionHtml}
                ${smsHtml}
            </div>
        `;

        // 启动倒计时
        if (isActive && order.expires_at) {
            this.startCountdown(order.expires_at);
        }
    }

    startCountdown(expiresAt) {
        const el = document.getElementById('order-countdown');
        if (!el) return;

        const update = () => {
            const remaining = Math.floor((new Date(expiresAt) - Date.now()) / 1000);
            if (remaining <= 0) {
                el.textContent = '00:00';
                clearInterval(this.countdownTimer);
                return;
            }
            el.textContent = Utils.formatCountdown(remaining);
        };
        update();
        this.countdownTimer = setInterval(update, 1000);
    }

    async activateOrder(orderId) {
        Utils.showLoading('获取号码中...');
        try {
            const res = await API.activateOrder(orderId);
            if (res.success) {
                Utils.toast('号码获取成功');
                this.loadOrderDetail(orderId);
            }
        } catch (e) {
            Utils.toast(e.message || '激活失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    async cancelOrder(orderId) {
        const ok = await Utils.confirm('确认取消', '取消后订单将变为已过期，是否继续？');
        if (!ok) return;

        Utils.showLoading();
        try {
            await API.cancelOrder(orderId);
            Utils.toast('订单已过期');
            this.navigate('orders');
        } catch (e) {
            Utils.toast(e.message || '取消失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    async copyPhone(phone) {
        const ok = await Utils.copyToClipboard(phone);
        Utils.toast(ok ? '号码已复制' : '复制失败', ok ? 'success' : 'error');
    }

    startPolling(orderId) {
        // 清除之前的轮询
        if (this.pollingTimers[orderId]) {
            clearInterval(this.pollingTimers[orderId]);
        }

        this.pollingTimers[orderId] = setInterval(async () => {
            try {
                const res = await API.getOrderSms(orderId);
                if (res.data && res.data.length > 0) {
                    // 有短信了，刷新页面
                    const orderRes = await API.getOrderDetail(orderId);
                    this.renderOrderDetail(orderRes.data, res.data);
                    clearInterval(this.pollingTimers[orderId]);
                }
            } catch (e) {
                console.error('Polling error:', e);
            }
        }, 5000);
    }

    // ===== 个人中心 =====
    async loadProfile() {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        try {
            const res = await API.getUserProfile(this.currentUser.id);
            if (res.success) {
                this.currentUser = { ...this.currentUser, ...res.data };
                Utils.setCurrentUser(this.currentUser);
            }
        } catch (e) {}
        this.renderProfile();
    }

    renderProfile() {
        const user = this.currentUser;
        const page = document.getElementById('page-profile');

        page.innerHTML = `
            <div class="profile-header">
                <div class="profile-avatar">👤</div>
                <div class="profile-name">${user.username || '用户'}</div>
                <div class="profile-id">ID: ${user.id?.slice(-8) || ''}</div>
            </div>
            <div class="balance-card">
                <div>
                    <div class="balance-label">我的积分</div>
                    <div class="balance-value">${Utils.formatPoints(user.balance || 0)}</div>
                </div>
                <button class="balance-action" onclick="app.navigate('recharge')">充值</button>
            </div>
            <div class="menu-list">
                <div class="menu-item" onclick="app.navigate('orders')">
                    <div class="menu-icon">📋</div>
                    <div class="menu-text">我的订单</div>
                    <div class="menu-arrow">›</div>
                </div>
                <div class="menu-item" onclick="app.navigate('favorites')">
                    <div class="menu-icon">⭐</div>
                    <div class="menu-text">我的收藏</div>
                    <div class="menu-arrow">›</div>
                </div>
                <div class="menu-item" onclick="app.navigate('notifications')">
                    <div class="menu-icon">🔔</div>
                    <div class="menu-text">消息通知</div>
                    <div class="menu-arrow">›</div>
                </div>
                <div class="menu-item" onclick="app.navigate('settings')">
                    <div class="menu-icon">⚙️</div>
                    <div class="menu-text">设置</div>
                    <div class="menu-arrow">›</div>
                </div>
            </div>
            <div style="padding:24px 16px;">
                <button class="btn btn-outline btn-block" style="color:var(--danger);border-color:var(--danger);" onclick="app.logout()">退出登录</button>
            </div>
        `;
    }

    // ===== 充值页面 =====
    async loadRecharge() {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        Utils.showLoading();
        try {
            const res = await API.getPointsPackages();
            this.renderRecharge(res.data || []);
        } catch (e) {
            Utils.toast('加载充值套餐失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderRecharge(packages) {
        const page = document.getElementById('page-recharge');
        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('profile')">←</button>
                <h1>积分充值</h1>
            </div>
            <div style="padding:16px;">
                <div class="card" style="text-align:center;">
                    <div class="balance-label">当前积分</div>
                    <div class="balance-value">${Utils.formatPoints(this.currentUser.balance || 0)}</div>
                </div>
                <div style="padding:16px;font-weight:700;">选择充值套餐</div>
                <div class="package-grid">
                    ${packages.map((p, i) => `
                        <div class="package-item ${i === 1 ? 'active' : ''}" onclick="app.selectPackage(this)">
                            <div class="package-points">${p.points || p.credits || 0}</div>
                            <div class="package-price">$${p.price || p.display_price || 0}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="recharge-hint">
                    <strong>💡 提示</strong>
                    H5 版本暂不支持在线支付。请下载 iOS App 进行充值，积分将自动同步到您的账号。
                </div>
                <button class="btn btn-primary btn-block" onclick="Utils.toast('请使用 iOS App 充值', 'warning')">前往 iOS App 充值</button>
            </div>
        `;
    }

    selectPackage(el) {
        document.querySelectorAll('.package-item').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }

    // ===== 收藏夹 =====
    async loadFavorites() {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        Utils.showLoading();
        try {
            const res = await API.getFavorites(this.currentUser.id);
            this.favorites = res.data || [];
            this.renderFavorites();
        } catch (e) {
            Utils.toast('加载收藏失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderFavorites() {
        const page = document.getElementById('page-favorites');
        if (!this.favorites.length) {
            page.innerHTML = `
                <div class="page-header">
                    <button class="back-btn" onclick="app.navigate('profile')">←</button>
                    <h1>我的收藏</h1>
                </div>
                <div class="empty-state"><div class="empty-icon">⭐</div><p>暂无收藏</p></div>
            `;
            return;
        }

        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('profile')">←</button>
                <h1>我的收藏</h1>
            </div>
            <div class="country-list" style="padding-top:16px;">
                ${this.favorites.map(f => `
                    <div class="country-item">
                        <div class="country-flag">${f.flag || '🏳️'}</div>
                        <div class="country-info">
                            <div class="country-name">${f.service_name || ''}</div>
                            <div class="country-code">${f.country_name || ''}</div>
                        </div>
                        <button class="fav-btn active" onclick="app.removeFavorite('${f.id}')">❤️</button>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async removeFavorite(favId) {
        try {
            await API.removeFavorite(favId);
            Utils.toast('已取消收藏');
            this.loadFavorites();
        } catch (e) {
            Utils.toast('操作失败', 'error');
        }
    }

    // ===== 通知 =====
    async loadNotifications() {
        if (!this.currentUser) {
            this.navigate('login');
            return;
        }
        Utils.showLoading();
        try {
            const res = await API.getNotifications(this.currentUser.id);
            this.renderNotifications(res.data || []);
        } catch (e) {
            Utils.toast('加载通知失败', 'error');
        } finally {
            Utils.hideLoading();
        }
    }

    renderNotifications(notifications) {
        const page = document.getElementById('page-notifications');
        if (!notifications.length) {
            page.innerHTML = `
                <div class="page-header">
                    <button class="back-btn" onclick="app.navigate('profile')">←</button>
                    <h1>消息通知</h1>
                </div>
                <div class="empty-state"><div class="empty-icon">🔔</div><p>暂无通知</p></div>
            `;
            return;
        }

        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('profile')">←</button>
                <h1>消息通知</h1>
                <button class="header-action" onclick="app.markAllRead()">全部已读</button>
            </div>
            <div style="padding:16px;">
                ${notifications.map(n => `
                    <div class="card" style="margin:0 0 8px 0;">
                        <div style="font-weight:600;margin-bottom:4px;">${n.title || '系统通知'}</div>
                        <div style="font-size:0.9rem;color:var(--text-secondary);">${n.content || ''}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:8px;">${Utils.formatDate(n.created_at)}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async markAllRead() {
        try {
            await API.markAllNotificationsRead(this.currentUser.id);
            Utils.toast('已全部标记为已读');
        } catch (e) {}
    }

    // ===== 设置 =====
    renderSettings() {
        const page = document.getElementById('page-settings');
        page.innerHTML = `
            <div class="page-header">
                <button class="back-btn" onclick="app.navigate('profile')">←</button>
                <h1>设置</h1>
            </div>
            <div class="menu-list" style="padding-top:16px;">
                <div class="menu-item" onclick="app.changeServer()">
                    <div class="menu-icon">🌐</div>
                    <div class="menu-text">切换服务器</div>
                    <div class="menu-arrow">›</div>
                </div>
                <div class="menu-item" onclick="app.clearCache()">
                    <div class="menu-icon">🗑️</div>
                    <div class="menu-text">清除缓存</div>
                    <div class="menu-arrow">›</div>
                </div>
                <div class="menu-item" onclick="app.showAbout()">
                    <div class="menu-icon">ℹ️</div>
                    <div class="menu-text">关于云码</div>
                    <div class="menu-arrow">›</div>
                </div>
            </div>
        `;
    }

    changeServer() {
        localStorage.removeItem('api_base_url');
        localStorage.removeItem('api_key');
        location.reload();
    }

    clearCache() {
        localStorage.removeItem('services_cache');
        localStorage.removeItem('countries_cache');
        Utils.toast('缓存已清除');
    }

    showAbout() {
        Utils.toast('云码 v1.0 - 全球虚拟号码接码平台');
    }

    logout() {
        Utils.clearAuth();
        this.currentUser = null;
        this.navigate('login');
        Utils.toast('已退出登录');
    }
}

// 初始化应用
const app = new App();
