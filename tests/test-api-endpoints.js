const http = require('http');
const url = require('url');

const BASE_URL = 'http://localhost:8088';
const DEFAULT_HEADERS = {
  'Content-Type': 'application/json',
  'X-API-Key': process.env.API_KEY || 'YOUR_API_KEY_HERE',
  'X-Device-Id': 'test-device-verify-' + Date.now()
};

const results = { passed: 0, failed: 0, tests: [] };

async function httpGet(path, headers = {}) {
  return new Promise((resolve, reject) => {
    const fullUrl = `${BASE_URL}${path}`;
    const req = http.get(fullUrl, { headers: { ...DEFAULT_HEADERS, ...headers } }, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(new Error(`解析失败: ${data.substring(0, 100)}`));
        }
      });
    });
    req.on('error', reject);
  });
}

async function httpPost(path, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const fullUrl = `${BASE_URL}${path}`;
    const postData = JSON.stringify(body);
    const req = http.request(fullUrl, {
      method: 'POST',
      headers: { ...DEFAULT_HEADERS, ...headers, 'Content-Length': Buffer.byteLength(postData) }
    }, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(new Error(`解析失败: ${data.substring(0, 100)}`));
        }
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

async function test(name, fn) {
  console.log(`\n📝 ${name}`);
  try {
    await fn();
    results.passed++;
    results.tests.push({ name, status: '✅' });
    console.log(`   ✅ 通过`);
  } catch (error) {
    results.failed++;
    results.tests.push({ name, status: '', error: error.message });
    console.log(`   ❌ 失败: ${error.message}`);
  }
}

async function runTest() {
  console.log('🔍 API端点完整验证\n');
  console.log('='.repeat(60));
  
  let token = '';
  let userId = '';
  let username = '';
  
  await test('1. 健康检查 /health', async () => {
    const res = await httpGet('/api.php?path=/health');
    if (!res) throw new Error('无响应');
    console.log(`   状态: ${res.status || 'ok'}`);
  });
  
  await test('2. API Key验证 /api-key', async () => {
    const res = await httpGet('/api.php?path=/api-key');
    if (!res) throw new Error('无响应');
    console.log(`   API Key响应: ${JSON.stringify(res).substring(0, 100)}`);
  });
  
  const testEmail = `verify_${Date.now()}@test.com`;
  await test('3. 用户注册 /auth/register', async () => {
    const res = await httpPost('/api.php?path=/auth/register', {
      username: '',
      password: 'test123456',
      email: testEmail,
      device_id: DEFAULT_HEADERS['X-Device-Id']
    });
    
    if (res.success !== true) throw new Error('注册失败: ' + res.message);
    if (!res.token) throw new Error('未返回token');
    if (!res.user) throw new Error('未返回user');
    
    token = res.token;
    userId = res.user.id;
    username = res.user.username;
    console.log(`   用户ID: ${userId}`);
    console.log(`   用户名: ${username}`);
    console.log(`   余额: ${res.user.balance} 积分`);
  });
  
  const authHeaders = { 'Authorization': `Bearer ${token}` };
  
  await test('4. 获取服务列表 /services', async () => {
    const res = await httpGet('/api.php?path=/services', authHeaders);
    if (res.success !== true) throw new Error('获取服务列表失败');
    if (!Array.isArray(res.data) || res.data.length === 0) {
      throw new Error('服务列表为空');
    }
    console.log(`   服务数量: ${res.data.length}`);
    console.log(`   第一个服务: ${res.data[0].name}`);
    console.log(`   图标URL: ${res.data[0].icon}`);
  });
  
  let firstCountryId = 1;
  await test('5. 获取服务国家列表 /service-countries', async () => {
    const res = await httpGet('/api.php?path=/service-countries&service_id=1', authHeaders);
    if (res.success !== true) throw new Error('获取国家列表失败: ' + res.message);
    if (!Array.isArray(res.data) || res.data.length === 0) {
      throw new Error('国家列表为空');
    }
    firstCountryId = res.data[0].country_id || res.data[0].id;
    console.log(`   国家数量: ${res.data.length}`);
    console.log(`   第一个国家: ${res.data[0].name} (${res.data[0].flag})`);
    console.log(`   价格: ${res.data[0].price} 积分`);
    console.log(`   country_id: ${firstCountryId}`);
  });
  
  await test('6. 计算价格 /price/calculate', async () => {
    const res = await httpGet(`/api.php?path=/price/calculate&service_id=1&country_id=${firstCountryId}`, authHeaders);
    if (res.success !== true) throw new Error('计算价格失败');
    console.log(`   成本: ${res.data.cost_price}`);
    console.log(`   售价: ${res.data.price_points} 积分`);
  });
  
  await test('7. 获取充值套餐 /payment/packages', async () => {
    const res = await httpGet('/api.php?path=/payment/packages', authHeaders);
    if (res.success !== true) throw new Error('获取充值套餐失败');
    if (!Array.isArray(res.data) || res.data.length === 0) {
      throw new Error('充值套餐列表为空');
    }
    console.log(`   套餐数量: ${res.data.length}`);
    console.log(`   第一个套餐: ${res.data[0].credits} 积分 - ${res.data[0].price}`);
  });
  
  await test('8. 获取通知列表 /notifications', async () => {
    const res = await httpGet(`/api.php?path=/notifications&user_id=${userId}`, authHeaders);
    if (res.success !== true) throw new Error('获取通知失败');
    console.log(`   通知数量: ${res.data.length}`);
    console.log(`   未读数量: ${res.unread_count}`);
  });
  
  await test('9. 标记所有通知为已读 /notifications/read-all', async () => {
    const res = await httpPost('/api.php?path=/notifications/read-all', { user_id: userId }, authHeaders);
    if (res.success !== true) throw new Error('标记失败');
    console.log(`   标记成功`);
  });
  
  await test('10. 获取交易记录 /user/transactions', async () => {
    const res = await httpGet(`/api.php?path=/user/transactions&user_id=${userId}&page=1&limit=10`, authHeaders);
    if (res.success !== true) throw new Error('获取交易记录失败');
    console.log(`   交易记录数量: ${res.data.length}`);
  });
  
  await test('11. 获取订单列表 /orders', async () => {
    const res = await httpGet('/api.php?path=/orders&page=1&limit=10', authHeaders);
    if (res.success !== true) throw new Error('获取订单列表失败');
    console.log(`   订单数量: ${res.data.length}`);
  });
  
  await test('12. 获取用户资料 /user/profile', async () => {
    const res = await httpGet('/api.php?path=/user/profile', authHeaders);
    if (res.success !== true) throw new Error('获取用户资料失败');
    console.log(`   用户名: ${res.data.username}`);
    console.log(`   余额: ${res.data.balance}`);
  });
  
  await test('13. 获取系统设置 /settings', async () => {
    const res = await httpGet('/api.php?path=/settings', authHeaders);
    if (res.success !== true) throw new Error('获取系统设置失败');
    console.log(`   设置项数量: ${res.data.length}`);
  });
  
  await test('14. 获取国家列表 /countries', async () => {
    const res = await httpGet('/api.php?path=/countries', authHeaders);
    if (res.success !== true) throw new Error('获取国家列表失败');
    console.log(`   国家数量: ${res.data.length}`);
  });
  
  await test('15. 获取已发布的服务国家 /service-countries/published', async () => {
    const res = await httpGet('/api.php?path=/service-countries/published', authHeaders);
    if (res.success !== true) throw new Error('获取已发布服务国家失败');
    console.log(`   已发布服务国家数量: ${res.data.length}`);
  });
  
  console.log('\n' + '='.repeat(60));
  console.log('📊 API验证报告');
  console.log('='.repeat(60));
  results.tests.forEach(t => {
    console.log(`${t.status} - ${t.name}`);
    if (t.error) console.log(`      ${t.error}`);
  });
  console.log('='.repeat(60));
  console.log(`✅ 通过: ${results.passed}`);
  console.log(` 失败: ${results.failed}`);
  console.log(` 总计: ${results.passed + results.failed}`);
  console.log('='.repeat(60));
  
  if (results.failed === 0) {
    console.log('\n🎉 所有API端点验证通过！\n');
  } else {
    console.log(`\n ️有 ${results.failed} 个端点验证失败\n`);
  }
}

runTest().catch(console.error);
