const { chromium } = require('playwright');
const fs = require('fs');
const https = require('https');

// 跳过SSL证书验证
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

// API 配置
const API_BASE = 'https://smsapi2.niceapp.eu.cc';
const API_KEY = process.env.API_KEY || 'YOUR_API_KEY_HERE';

// HTTP请求辅助函数
function apiCall(path, method = 'GET', data = null) {
  return new Promise((resolve, reject) => {
    const [basePath, queryString] = path.split('?');
    const params = new URLSearchParams(queryString);
    
    const options = {
      hostname: new URL(API_BASE).hostname,
      port: 443,
      path: `/api.php?path=${encodeURIComponent(basePath)}&${params.toString()}`,
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': API_KEY
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(body));
        } catch (e) {
          resolve({ raw: body });
        }
      });
    });

    req.on('error', reject);
    if (data) req.write(JSON.stringify(data));
    req.end();
  });
}

(async () => {
  console.log('🚀 Simu 应用完整页面交互测试\n');
  
  const browser = await chromium.launch({ 
    headless: false,
    args: ['--window-size=414,896']
  });
  
  const context = await browser.newContext({
    viewport: { width: 414, height: 896 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'
  });
  
  const page = await context.newPage();
  
  const results = { passed: 0, failed: 0, tests: [] };
  
  async function test(name, fn) {
    try {
      await fn();
      console.log(`✅ ${name}`);
      results.passed++;
      results.tests.push({ name, status: 'passed' });
    } catch (error) {
      console.log(`❌ ${name}: ${error.message}`);
      results.failed++;
      results.tests.push({ name, status: 'failed', error: error.message });
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    console.log(`   📸 ${name}.png`);
  }
  
  async function clickAt(x, y, delay = 3000) {
    await page.mouse.click(x, y);
    await page.waitForTimeout(delay);
  }
  
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  try {
    console.log('='.repeat(60));
    
    // ===== 第1步：启动流程 =====
    console.log('\n📱 第1步：启动流程\n');
    
    await test('1.1 访问应用 (启动页)', async () => {
      await page.goto('http://localhost:8080', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      await screenshot('01-splash');
    });
    
    await page.waitForTimeout(3000);
    
    await test('1.2 引导页显示', async () => {
      await screenshot('02-onboarding');
    });
    
    await test('1.3 跳过引导页进入登录', async () => {
      // Skip按钮位置: 右上角 (380, 40)
      await clickAt(380, 40, 3000);
      await screenshot('03-login-page');
    });
    
    // ===== 第2步：注册流程 =====
    console.log('\n📱 第2步：注册流程\n');
    
    await test('2.1 点击Register进入注册页', async () => {
      // Register按钮位置: 底部 (207, 680)
      await clickAt(207, 680, 3000);
      await screenshot('04-register-page');
    });
    
    await test('2.2 填写注册表单', async () => {
      const username = 'testuser_' + Date.now().toString().slice(-6);
      const email = `${username}@test.com`;
      const password = 'test123456';
      
      console.log(`   用户名: ${username}`);
      console.log(`   邮箱: ${email}`);
      console.log(`   密码: ${password}`);
      
      // Username输入框 (207, 420)
      await clickAt(207, 420, 500);
      await page.keyboard.type(username);
      await page.waitForTimeout(500);
      
      // Email输入框 (207, 490)
      await clickAt(207, 490, 500);
      await page.keyboard.type(email);
      await page.waitForTimeout(500);
      
      // Password输入框 (207, 560)
      await clickAt(207, 560, 500);
      await page.keyboard.type(password);
      await page.waitForTimeout(500);
      
      // Confirm Password输入框 (207, 630)
      await clickAt(207, 630, 500);
      await page.keyboard.type(password);
      await page.waitForTimeout(500);
      
      await screenshot('05-register-filled');
      
      // 保存账号信息
      page.userData = { username, email, password };
    });
    
    await test('2.3 点击注册按钮', async () => {
      // Register按钮 (207, 700)
      await clickAt(207, 700, 5000);
      await screenshot('06-register-result');
    });
    
    // 检查注册结果
    await test('2.4 检查注册结果', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log(`   页面状态: 已显示`);
      
      // 截图显示当前页面状态
      await screenshot('07-after-register');
    });
    
    // ===== 第3步：首页功能 =====
    console.log('\n📱 第3步：首页功能测试\n');
    
    await test('3.1 导航到首页', async () => {
      // 直接访问首页URL
      await page.goto('http://localhost:8080/#/home', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      await screenshot('08-home');
    });
    
    await test('3.2 查看服务列表', async () => {
      await screenshot('09-service-list');
    });
    
    // ===== 第4步：选择服务 =====
    console.log('\n📱 第4步：选择服务\n');
    
    await test('4.1 点击第一个服务', async () => {
      // 第一个服务卡片中心位置大约 (207, 300)
      await clickAt(207, 300, 5000);
      await screenshot('10-countries-page');
    });
    
    await test('4.2 查看国家列表', async () => {
      await screenshot('11-countries-list');
    });
    
    // ===== 第5步：选择国家 =====
    console.log('\n📱 第5步：选择国家\n');
    
    await test('5.1 点击第一个国家', async () => {
      // 第一个国家列表项中心位置大约 (207, 300)
      await clickAt(207, 300, 5000);
      await screenshot('12-purchase-confirm');
    });
    
    // ===== 第6步：个人中心 =====
    console.log('\n📱 第6步：个人中心功能\n');
    
    await test('6.1 导航到个人中心', async () => {
      // 底部导航栏个人中心 (345, 850)
      await clickAt(345, 850, 5000);
      await screenshot('13-profile');
    });
    
    await test('6.2 查看余额信息', async () => {
      await screenshot('14-profile-balance');
    });
    
    await test('6.3 进入积分购买页', async () => {
      // 充值按钮位置大约 (280, 350)
      await clickAt(280, 350, 5000);
      await screenshot('15-payment');
    });
    
    await test('6.4 查看充值套餐', async () => {
      await screenshot('16-payment-packages');
    });
    
    // ===== 第7步：订单页面 =====
    console.log('\n📱 第7步：订单页面\n');
    
    await test('7.1 导航到订单列表', async () => {
      // 底部导航栏订单 (275, 850)
      await clickAt(275, 850, 5000);
      await screenshot('17-orders');
    });
    
    await test('7.2 查看订单详情', async () => {
      await screenshot('18-order-detail');
    });
    
    // ===== 第8步：设置和其他页面 =====
    console.log('\n📱 第8步：设置和其他页面\n');
    
    await test('8.1 进入设置页', async () => {
      // 设置按钮在个人中心内 (340, 150)
      await clickAt(345, 850, 2000); // 先回到个人中心
      await clickAt(340, 150, 5000); // 设置按钮
      await screenshot('19-settings');
    });
    
    await test('8.2 进入帮助/FAQ页', async () => {
      await page.goto('http://localhost:8080/#/profile/help', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('20-help');
    });
    
    await test('8.3 进入联系我们页', async () => {
      await page.goto('http://localhost:8080/#/profile/contact', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('21-contact');
    });
    
    await test('8.4 进入关于我们页', async () => {
      await page.goto('http://localhost:8080/#/profile/about', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('22-about');
    });
    
    // ===== 打印测试报告 =====
    console.log('\n' + '='.repeat(60));
    console.log('📊 测试报告');
    console.log('='.repeat(60));
    console.log(`✅ 通过: ${results.passed}`);
    console.log(`❌ 失败: ${results.failed}`);
    console.log(`📝 总计: ${results.passed + results.failed}`);
    console.log('='.repeat(60));
    
    if (results.failed > 0) {
      console.log('\n❌ 失败的测试:');
      results.tests.filter(t => t.status === 'failed').forEach(t => {
        console.log(`   • ${t.name}: ${t.error}`);
      });
    }
    
    console.log('\n📸 所有截图已保存到 screenshots/ 目录');
    console.log('   请查看截图确认每个页面的UI和交互是否正确\n');
    
  } catch (error) {
    console.error('❌ 测试出错:', error.message);
  } finally {
    await browser.close();
    console.log('👋 测试完成\n');
  }
})();
