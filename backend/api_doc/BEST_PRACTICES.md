# API 最佳实践指南

本文档提供SMS接码平台API的最佳实践建议，帮助开发者构建稳定、高效、安全的客户端应用。

---

## 目录

1. [认证与安全](#认证与安全)
2. [请求优化](#请求优化)
3. [错误处理](#错误处理)
4. [状态管理](#状态管理)
5. [用户体验](#用户体验)
6. [性能优化](#性能优化)
7. [测试建议](#测试建议)

---

## 认证与安全

### ✅ DO - 推荐做法

#### 1. 安全存储Token

```javascript
// ✅ 使用安全存储
// Web: localStorage/sessionStorage
localStorage.setItem('auth_token', token);

// iOS: Keychain
import * as Keychain from 'react-native-keychain';
await Keychain.setGenericPassword('auth_token', token);

// Android: EncryptedSharedPreferences
import EncryptedStorage from 'react-native-encrypted-storage';
await EncryptedStorage.setItem('auth_token', token);
```

#### 2. 不要硬编码API Key

```javascript
// ✅ 从环境变量读取
const API_KEY = process.env.REACT_APP_API_KEY;

// ✅ 从配置文件读取
import config from './config';
const API_KEY = config.apiKey;
```

#### 3. 实现Token刷新机制

```javascript
// ✅ Token过期时自动刷新
async function makeRequest(url, options) {
  try {
    return await fetch(url, options);
  } catch (error) {
    if (error.response?.status === 401) {
      // Token过期，尝试刷新
      const newToken = await refreshToken();
      if (newToken) {
        // 使用新Token重试
        options.headers.Authorization = `Bearer ${newToken}`;
        return await fetch(url, options);
      }
    }
    throw error;
  }
}
```

#### 4. 使用HTTPS

```javascript
// ✅ 始终使用HTTPS
const API_BASE = 'https://sms.niceapp.eu.cc/api';

// ❌ 不要使用HTTP
// const API_BASE = 'http://sms.niceapp.eu.cc/api';
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要在代码中硬编码敏感信息
const API_KEY = '606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077';

// ❌ 不要在URL中传递Token
const url = `${API_BASE}/orders?token=${token}`;

// ❌ 不要在日志中打印敏感信息
console.log('Token:', token);
```

---

## 请求优化

### ✅ DO - 推荐做法

#### 1. 实现请求拦截器

```javascript
// ✅ 统一添加认证头
axios.interceptors.request.use(config => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers['X-API-Key'] = API_KEY;
  return config;
});
```

#### 2. 实现请求重试

```javascript
// ✅ 网络错误时自动重试
async function fetchWithRetry(url, options, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fetch(url, options);
    } catch (error) {
      // 最后一次重试失败则抛出错误
      if (i === maxRetries - 1) throw error;
      
      // 指数退避
      await sleep(Math.pow(2, i) * 1000);
    }
  }
}
```

#### 3. 设置合理的超时时间

```javascript
// ✅ 设置超时
const controller = new AbortController();
const timeoutId = setTimeout(() => controller.abort(), 30000); // 30秒

try {
  const response = await fetch(url, {
    signal: controller.signal,
    ...options
  });
  clearTimeout(timeoutId);
  return response;
} catch (error) {
  if (error.name === 'AbortError') {
    throw new Error('请求超时');
  }
  throw error;
}
```

#### 4. 合并并发请求

```javascript
// ✅ 使用Promise.all并发请求
const [services, balance, orders] = await Promise.all([
  api.getServices(),
  api.getBalance(),
  api.getOrders()
]);
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要串行请求独立数据
const services = await api.getServices();
const balance = await api.getBalance();
const orders = await api.getOrders();

// ❌ 不要过于频繁地轮询
setInterval(() => checkOrderStatus(), 1000); // 每秒一次太频繁

// ❌ 不要无限重试
while (true) {
  try {
    await makeRequest();
    break;
  } catch (error) {
    // 无限循环
  }
}
```

---

## 错误处理

### ✅ DO - 推荐做法

#### 1. 统一错误处理

```javascript
// ✅ 创建统一的错误处理器
class ApiError extends Error {
  constructor(message, code, status) {
    super(message);
    this.code = code;
    this.status = status;
  }
}

function handleApiError(error) {
  if (error.response) {
    const { status, data } = error.response;
    throw new ApiError(
      data.error || '请求失败',
      data.code,
      status
    );
  } else if (error.request) {
    throw new ApiError('网络连接失败', 'network_error', 0);
  } else {
    throw new ApiError('请求配置错误', 'config_error', 0);
  }
}
```

#### 2. 提供用户友好的错误提示

```javascript
// ✅ 根据错误码显示友好提示
function showErrorMessage(error) {
  const messages = {
    'insufficient_balance': '积分不足，请先充值',
    'order_expired': '订单已过期',
    'network_error': '网络连接失败，请检查网络设置',
    'server_error': '服务器繁忙，请稍后重试'
  };
  
  const message = messages[error.code] || error.message;
  showToast(message);
}
```

#### 3. 记录错误日志

```javascript
// ✅ 记录错误用于调试
function logError(error, context) {
  const errorLog = {
    timestamp: new Date().toISOString(),
    message: error.message,
    code: error.code,
    status: error.status,
    context,
    userAgent: navigator.userAgent
  };
  
  // 发送到日志服务
  if (process.env.NODE_ENV === 'production') {
    sendToLogService(errorLog);
  } else {
    console.error('API Error:', errorLog);
  }
}
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要忽略错误
try {
  await api.createOrder();
} catch (error) {
  // 什么都不做
}

// ❌ 不要向用户显示技术错误
alert(error.stack);

// ❌ 不要在catch中再次抛出相同错误
try {
  await api.createOrder();
} catch (error) {
  throw error; // 没有添加任何价值
}
```

---

## 状态管理

### ✅ DO - 推荐做法

#### 1. 使用状态管理库

```javascript
// ✅ 使用Redux/Zustand管理全局状态
// Zustand示例
import create from 'zustand';

const useAuthStore = create((set) => ({
  user: null,
  token: null,
  login: (user, token) => set({ user, token }),
  logout: () => set({ user: null, token: null })
}));

const useOrderStore = create((set) => ({
  orders: [],
  currentOrder: null,
  setOrders: (orders) => set({ orders }),
  setCurrentOrder: (order) => set({ currentOrder: order })
}));
```

#### 2. 实现数据缓存

```javascript
// ✅ 缓存不常变化的数据
const cache = new Map();

async function getServicesWithCache() {
  const cacheKey = 'services';
  const cached = cache.get(cacheKey);
  
  // 缓存有效期1小时
  if (cached && Date.now() - cached.timestamp < 3600000) {
    return cached.data;
  }
  
  const data = await api.getServices();
  cache.set(cacheKey, {
    data,
    timestamp: Date.now()
  });
  
  return data;
}
```

#### 3. 乐观更新

```javascript
// ✅ 先更新UI，再发送请求
async function cancelOrder(orderId) {
  // 乐观更新
  updateOrderStatus(orderId, 'cancelled');
  
  try {
    await api.cancelOrder(orderId);
  } catch (error) {
    // 失败时回滚
    revertOrderStatus(orderId);
    showError('取消失败');
  }
}
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要在组件中直接存储全局状态
let globalUser = null; // 全局变量

// ❌ 不要过度缓存动态数据
// 订单状态频繁变化，不应长时间缓存
const cachedOrders = localStorage.getItem('orders');

// ❌ 不要忘记清理过期缓存
// 缓存会无限增长
```

---

## 用户体验

### ✅ DO - 推荐做法

#### 1. 显示加载状态

```javascript
// ✅ 请求时显示加载指示器
async function loadOrders() {
  setLoading(true);
  try {
    const orders = await api.getOrders();
    setOrders(orders);
  } finally {
    setLoading(false);
  }
}
```

#### 2. 实现下拉刷新

```javascript
// ✅ 支持下拉刷新
async function handleRefresh() {
  setRefreshing(true);
  try {
    await loadOrders();
  } finally {
    setRefreshing(false);
  }
}
```

#### 3. 显示操作反馈

```javascript
// ✅ 操作成功/失败时给予反馈
async function createOrder(serviceId, countryId) {
  try {
    const order = await api.createOrder(serviceId, countryId);
    showSuccess('订单创建成功');
    navigateToOrder(order.id);
  } catch (error) {
    showError('创建失败: ' + error.message);
  }
}
```

#### 4. 实现离线提示

```javascript
// ✅ 检测网络状态
window.addEventListener('online', () => {
  showToast('网络已连接');
  retryFailedRequests();
});

window.addEventListener('offline', () => {
  showToast('网络已断开');
});
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要让用户等待时没有反馈
await api.createOrder(); // 用户不知道是否在处理

// ❌ 不要在操作完成后没有提示
await api.cancelOrder(orderId); // 用户不知道是否成功

// ❌ 不要阻塞UI
while (loading) {
  // 阻塞主线程
}
```

---

## 性能优化

### ✅ DO - 推荐做法

#### 1. 实现分页加载

```javascript
// ✅ 分页加载订单列表
async function loadOrders(page = 1) {
  const response = await api.getOrders({
    page,
    limit: 20
  });
  
  if (page === 1) {
    setOrders(response.data);
  } else {
    setOrders(prev => [...prev, ...response.data]);
  }
  
  setHasMore(response.pagination.current_page < response.pagination.total_pages);
}
```

#### 2. 防抖和节流

```javascript
// ✅ 搜索时使用防抖
import { debounce } from 'lodash';

const searchServices = debounce(async (keyword) => {
  const results = await api.searchServices(keyword);
  setSearchResults(results);
}, 300);

// ✅ 滚动加载时使用节流
import { throttle } from 'lodash';

const handleScroll = throttle(() => {
  if (isNearBottom() && hasMore && !loading) {
    loadMore();
  }
}, 200);
```

#### 3. 图片懒加载

```javascript
// ✅ 服务图标懒加载
<img 
  src={service.icon} 
  loading="lazy"
  alt={service.name}
/>
```

#### 4. 请求取消

```javascript
// ✅ 组件卸载时取消请求
useEffect(() => {
  const controller = new AbortController();
  
  fetchOrders(controller.signal);
  
  return () => {
    controller.abort();
  };
}, []);
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要一次加载所有数据
const allOrders = await api.getOrders({ limit: 999999 });

// ❌ 不要在每次输入时都发送请求
onChange={(e) => searchServices(e.target.value)}

// ❌ 不要忘记清理定时器
setInterval(() => checkStatus(), 5000);
// 组件卸载时定时器仍在运行
```

---

## 测试建议

### ✅ DO - 推荐做法

#### 1. 单元测试

```javascript
// ✅ 测试API客户端
describe('API Client', () => {
  it('should register device', async () => {
    const result = await api.registerDevice('test_device');
    expect(result.success).toBe(true);
    expect(result.token).toBeDefined();
  });
  
  it('should handle insufficient balance', async () => {
    await expect(
      api.createOrder(1, 1)
    ).rejects.toThrow('insufficient_balance');
  });
});
```

#### 2. 集成测试

```javascript
// ✅ 测试完整流程
describe('Order Flow', () => {
  it('should complete order flow', async () => {
    // 注册
    const { token } = await api.registerDevice('test');
    
    // 创建订单
    const order = await api.createOrder(1, 1);
    expect(order.status).toBe('pending');
    
    // 激活订单
    const activeOrder = await api.activateOrder(order.id);
    expect(activeOrder.status).toBe('active');
    expect(activeOrder.phone_number).toBeDefined();
  });
});
```

#### 3. Mock数据

```javascript
// ✅ 使用Mock数据测试
jest.mock('./api', () => ({
  getServices: jest.fn(() => Promise.resolve([
    { id: 1, name: 'WhatsApp' }
  ])),
  createOrder: jest.fn(() => Promise.resolve({
    id: 'order_123',
    status: 'pending'
  }))
}));
```

### ❌ DON'T - 避免做法

```javascript
// ❌ 不要在测试中使用真实API
test('create order', async () => {
  const order = await fetch('https://sms.niceapp.eu.cc/api/orders', {
    method: 'POST',
    // 真实请求会消耗积分
  });
});

// ❌ 不要忽略错误情况测试
test('create order', async () => {
  const order = await api.createOrder(1, 1);
  expect(order).toBeDefined();
  // 没有测试失败情况
});
```

---

## 订单轮询最佳实践

### ✅ 推荐实现

```javascript
class OrderPoller {
  constructor(orderId, onUpdate, onComplete) {
    this.orderId = orderId;
    this.onUpdate = onUpdate;
    this.onComplete = onComplete;
    this.interval = null;
    this.retryCount = 0;
    this.maxRetries = 3;
  }
  
  start() {
    // 立即检查一次
    this.check();
    
    // 每5秒检查一次
    this.interval = setInterval(() => this.check(), 5000);
  }
  
  async check() {
    try {
      const order = await api.getOrderDetail(this.orderId);
      this.onUpdate(order);
      
      // 收到验证码或订单完成
      if (order.sms_code || order.status === 'completed') {
        this.stop();
        this.onComplete(order);
      }
      
      // 重置重试计数
      this.retryCount = 0;
      
    } catch (error) {
      this.retryCount++;
      
      if (this.retryCount >= this.maxRetries) {
        this.stop();
        showError('获取订单状态失败');
      }
    }
  }
  
  stop() {
    if (this.interval) {
      clearInterval(this.interval);
      this.interval = null;
    }
  }
}

// 使用示例
const poller = new OrderPoller(
  orderId,
  (order) => updateUI(order),
  (order) => showSuccess(`收到验证码: ${order.sms_code}`)
);

poller.start();

// 组件卸载时停止
onUnmount(() => poller.stop());
```

---

## 积分不足处理

### ✅ 推荐实现

```javascript
async function handleInsufficientBalance(error) {
  const { required_points, current_balance } = error.data;
  const needed = required_points - current_balance;
  
  const result = await showDialog({
    title: '积分不足',
    message: `需要 ${required_points} 积分，当前 ${current_balance} 积分\n还需充值 ${needed} 积分`,
    buttons: [
      { text: '取消', role: 'cancel' },
      { text: '去充值', role: 'confirm' }
    ]
  });
  
  if (result === 'confirm') {
    // 跳转到充值页面，并预选合适的套餐
    const packages = await api.getPointPackages();
    const suitable = packages.find(p => p.credits >= needed);
    
    navigateToTopup({
      recommended: suitable?.id
    });
  }
}
```

---

## 安全检查清单

- [ ] Token安全存储（Keychain/EncryptedStorage）
- [ ] API Key不硬编码在代码中
- [ ] 使用HTTPS通信
- [ ] 实现Token刷新机制
- [ ] 不在日志中打印敏感信息
- [ ] 不在URL中传递Token
- [ ] 实现请求签名（如需要）
- [ ] 设置合理的超时时间
- [ ] 实现请求重试机制
- [ ] 验证服务器证书

---

## 性能检查清单

- [ ] 实现请求缓存
- [ ] 使用分页加载
- [ ] 实现图片懒加载
- [ ] 合并并发请求
- [ ] 实现防抖和节流
- [ ] 组件卸载时取消请求
- [ ] 清理定时器和监听器
- [ ] 优化轮询频率
- [ ] 实现离线缓存
- [ ] 压缩请求数据

---

## 用户体验检查清单

- [ ] 显示加载状态
- [ ] 显示操作反馈
- [ ] 实现下拉刷新
- [ ] 实现错误重试
- [ ] 显示网络状态
- [ ] 实现乐观更新
- [ ] 提供友好的错误提示
- [ ] 实现骨架屏
- [ ] 支持离线模式
- [ ] 实现数据预加载

---

## 总结

遵循这些最佳实践可以帮助你：

1. **提高安全性** - 保护用户数据和Token
2. **提升性能** - 减少不必要的请求和渲染
3. **改善体验** - 提供流畅的用户交互
4. **降低错误** - 优雅处理各种异常情况
5. **便于维护** - 代码结构清晰，易于调试

记住：**好的API集成不仅仅是能用，更要好用、安全、高效！**

---

## 相关文档

- [API文档](README.md)
- [快速开始](QUICK_START.md)
- [错误处理](ERROR_HANDLING.md)
- [Postman集合](SMS_API.postman_collection.json)

---

**文档版本**: v1.0  
**最后更新**: 2026-05-12
