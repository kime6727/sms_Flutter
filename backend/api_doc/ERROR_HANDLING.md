# 错误处理指南

## 错误响应格式

所有错误响应遵循统一格式：

```json
{
  "success": false,
  "error": "错误描述信息",
  "code": "error_code"  // 可选，机器可读的错误码
}
```

---

## HTTP状态码

| 状态码 | 说明 | 处理建议 |
|--------|------|---------|
| 200 | 成功 | 正常处理响应数据 |
| 400 | 请求参数错误 | 检查请求参数，修正后重试 |
| 401 | 未授权 | 检查API Key或Token，重新登录 |
| 404 | 资源不存在 | 检查请求路径和资源ID |
| 409 | 冲突 | 资源已存在，如邮箱重复 |
| 500 | 服务器错误 | 稍后重试，持续失败联系支持 |

---

## 错误码详解

### 认证错误

#### `invalid_api_key`
**说明**: API Key无效或缺失  
**HTTP状态码**: 401  
**处理方式**:
```javascript
if (error.code === 'invalid_api_key') {
  // 检查API Key配置
  console.error('API Key配置错误');
  // 联系技术支持获取正确的API Key
}
```

#### `unauthorized`
**说明**: 未授权访问  
**HTTP状态码**: 401  
**处理方式**:
```javascript
if (error.code === 'unauthorized') {
  // Token无效或过期
  // 清除本地Token
  localStorage.removeItem('token');
  // 跳转到登录页
  router.push('/login');
}
```

#### `invalid_token`
**说明**: Token格式无效  
**HTTP状态码**: 401  
**处理方式**:
```javascript
if (error.code === 'invalid_token') {
  // Token格式错误
  // 清除并重新登录
  await reLogin();
}
```

#### `token_expired`
**说明**: Token已过期  
**HTTP状态码**: 401  
**处理方式**:
```javascript
if (error.code === 'token_expired') {
  // 尝试刷新Token
  const newToken = await refreshToken();
  if (newToken) {
    // 使用新Token重试请求
    return retryRequest(newToken);
  } else {
    // 刷新失败，重新登录
    router.push('/login');
  }
}
```

---

### 注册/登录错误

#### `email_exists`
**说明**: 邮箱已被使用  
**HTTP状态码**: 409  
**处理方式**:
```javascript
if (error.code === 'email_exists') {
  showError('该邮箱已被注册，请使用其他邮箱或直接登录');
  // 提供登录入口
  showLoginOption();
}
```

#### `invalid_email`
**说明**: 邮箱格式无效  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'invalid_email') {
  showError('请输入有效的邮箱地址');
  // 高亮邮箱输入框
  highlightEmailField();
}
```

#### `password_too_short`
**说明**: 密码少于6位  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'password_too_short') {
  showError('密码至少需要6位字符');
  // 显示密码强度提示
  showPasswordStrengthHint();
}
```

#### `invalid_credentials`
**说明**: 用户名或密码错误  
**HTTP状态码**: 401  
**处理方式**:
```javascript
if (error.code === 'invalid_credentials') {
  showError('用户名或密码错误，请重试');
  // 增加登录失败计数
  incrementLoginFailCount();
  // 多次失败后显示找回密码
  if (loginFailCount >= 3) {
    showForgotPasswordLink();
  }
}
```

---

### 订单错误

#### `insufficient_balance`
**说明**: 积分不足  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'insufficient_balance') {
  const required = error.required_points || 0;
  const current = error.current_balance || 0;
  const needed = required - current;
  
  showDialog({
    title: '积分不足',
    message: `需要 ${required} 积分，当前 ${current} 积分，还需 ${needed} 积分`,
    actions: [
      { text: '取消', action: 'cancel' },
      { text: '去充值', action: () => router.push('/topup') }
    ]
  });
}
```

#### `order_not_found`
**说明**: 订单不存在  
**HTTP状态码**: 404  
**处理方式**:
```javascript
if (error.code === 'order_not_found') {
  showError('订单不存在或已被删除');
  // 返回订单列表
  router.push('/orders');
}
```

#### `order_expired`
**说明**: 订单已过期  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'order_expired') {
  showError('订单已过期，无法操作');
  // 刷新订单列表
  refreshOrderList();
}
```

#### `order_already_activated`
**说明**: 订单已激活  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'order_already_activated') {
  showInfo('订单已激活，请查看订单详情');
  // 跳转到订单详情
  router.push(`/orders/${orderId}`);
}
```

#### `cannot_cancel`
**说明**: 订单不可取消  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'cannot_cancel') {
  showError('该订单当前状态不允许取消');
  // 显示订单状态说明
  showOrderStatusHelp();
}
```

#### `no_available_numbers`
**说明**: 暂无可用号码  
**HTTP状态码**: 400  
**处理方式**:
```javascript
if (error.code === 'no_available_numbers') {
  showError('该服务暂时无可用号码，请稍后再试或选择其他国家');
  // 推荐其他国家
  showAlternativeCountries();
}
```

---

### 资源错误

#### `service_not_found`
**说明**: 服务不存在  
**HTTP状态码**: 404  
**处理方式**:
```javascript
if (error.code === 'service_not_found') {
  showError('服务不存在或已下架');
  // 返回服务列表
  router.push('/services');
}
```

#### `country_not_found`
**说明**: 国家不存在  
**HTTP状态码**: 404  
**处理方式**:
```javascript
if (error.code === 'country_not_found') {
  showError('该国家暂不支持此服务');
  // 显示支持的国家列表
  showSupportedCountries();
}
```

---

## 错误处理最佳实践

### 1. 统一错误处理器

```javascript
class ApiErrorHandler {
  static handle(error) {
    // 网络错误
    if (!error.response) {
      return this.handleNetworkError(error);
    }
    
    // HTTP错误
    const { status, data } = error.response;
    
    switch (status) {
      case 400:
        return this.handleBadRequest(data);
      case 401:
        return this.handleUnauthorized(data);
      case 404:
        return this.handleNotFound(data);
      case 409:
        return this.handleConflict(data);
      case 500:
        return this.handleServerError(data);
      default:
        return this.handleUnknownError(error);
    }
  }
  
  static handleNetworkError(error) {
    showError('网络连接失败，请检查网络设置');
    // 记录错误日志
    logError('network_error', error);
  }
  
  static handleUnauthorized(data) {
    // 清除Token
    clearAuth();
    // 跳转登录
    router.push('/login');
    showError('登录已过期，请重新登录');
  }
  
  static handleBadRequest(data) {
    // 根据错误码处理
    if (data.code) {
      this.handleErrorCode(data.code, data);
    } else {
      showError(data.error || '请求参数错误');
    }
  }
  
  static handleErrorCode(code, data) {
    const handlers = {
      'insufficient_balance': () => this.handleInsufficientBalance(data),
      'email_exists': () => showError('邮箱已被使用'),
      'order_expired': () => this.handleOrderExpired(data),
      // ... 更多错误码处理
    };
    
    const handler = handlers[code];
    if (handler) {
      handler();
    } else {
      showError(data.error);
    }
  }
}
```

### 2. 请求重试机制

```javascript
async function requestWithRetry(fn, maxRetries = 3) {
  let lastError;
  
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn();
    } catch (error) {
      lastError = error;
      
      // 不重试的错误
      if (error.response?.status === 401 || 
          error.response?.status === 404) {
        throw error;
      }
      
      // 等待后重试
      if (i < maxRetries - 1) {
        await sleep(Math.pow(2, i) * 1000); // 指数退避
      }
    }
  }
  
  throw lastError;
}

// 使用示例
const data = await requestWithRetry(() => 
  api.getOrderDetail(orderId)
);
```

### 3. 用户友好的错误提示

```javascript
const ERROR_MESSAGES = {
  'insufficient_balance': {
    title: '积分不足',
    message: '您的积分不足以完成此操作',
    action: '去充值',
    actionHandler: () => router.push('/topup')
  },
  'order_expired': {
    title: '订单已过期',
    message: '该订单已超过有效期',
    action: '查看其他订单',
    actionHandler: () => router.push('/orders')
  },
  'network_error': {
    title: '网络错误',
    message: '无法连接到服务器，请检查网络连接',
    action: '重试',
    actionHandler: () => location.reload()
  }
};

function showFriendlyError(code) {
  const config = ERROR_MESSAGES[code];
  if (config) {
    showDialog({
      title: config.title,
      message: config.message,
      actions: [
        { text: '取消', role: 'cancel' },
        { text: config.action, handler: config.actionHandler }
      ]
    });
  } else {
    showError('操作失败，请稍后重试');
  }
}
```

### 4. 错误日志记录

```javascript
class ErrorLogger {
  static log(error, context = {}) {
    const errorLog = {
      timestamp: new Date().toISOString(),
      error: {
        message: error.message,
        code: error.code,
        status: error.response?.status
      },
      context: {
        url: error.config?.url,
        method: error.config?.method,
        userId: getCurrentUserId(),
        ...context
      },
      stack: error.stack
    };
    
    // 发送到日志服务
    this.sendToLogService(errorLog);
    
    // 开发环境打印
    if (process.env.NODE_ENV === 'development') {
      console.error('API Error:', errorLog);
    }
  }
  
  static sendToLogService(log) {
    // 发送到日志收集服务
    // 如 Sentry, LogRocket 等
  }
}
```

---

## 错误处理检查清单

- [ ] 实现统一的错误处理器
- [ ] 为所有API调用添加try-catch
- [ ] 实现请求重试机制
- [ ] 提供用户友好的错误提示
- [ ] 记录错误日志用于调试
- [ ] 处理网络超时和断网情况
- [ ] 401错误时清除Token并跳转登录
- [ ] 积分不足时引导用户充值
- [ ] 订单过期时刷新订单列表
- [ ] 服务器错误时提供重试选项

---

## 示例：完整的API调用

```javascript
async function createOrder(serviceId, countryId) {
  try {
    // 显示加载状态
    showLoading('创建订单中...');
    
    // 发起请求（带重试）
    const response = await requestWithRetry(() =>
      api.post('/orders', {
        service_id: serviceId,
        country_id: countryId
      })
    );
    
    // 隐藏加载状态
    hideLoading();
    
    // 处理成功响应
    if (response.data.success) {
      showSuccess('订单创建成功');
      return response.data.data;
    }
    
  } catch (error) {
    // 隐藏加载状态
    hideLoading();
    
    // 统一错误处理
    ApiErrorHandler.handle(error);
    
    // 记录错误日志
    ErrorLogger.log(error, {
      action: 'create_order',
      serviceId,
      countryId
    });
    
    // 抛出错误供上层处理
    throw error;
  }
}
```

---

## 总结

良好的错误处理能够：
1. 提升用户体验
2. 快速定位问题
3. 减少用户困惑
4. 提高应用稳定性

记住：**永远不要让用户看到原始的技术错误信息！**
