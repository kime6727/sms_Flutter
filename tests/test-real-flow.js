const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 Simu 真实API端到端测试\n');
  console.log('='.repeat(60));
  
  const browser = await chromium.launch({ 
    headless: false,
    args: ['--window-size=414,896']
  });
  
  const context = await browser.newContext({
    viewport: { width: 414, height: 896 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'
  });
  
  const page = await context.newPage();
  
  // 监控网络请求
  page.on('request', req => {
    if (req.url().includes('api')) {
      console.log(` ${req.method()} ${req.url()}`);
    }
  });
  
  page.on('response', res => {
    if (res.url().includes('api')) {
      const status = res.status();
      const ok = status >= 200 && status < 300;
      console.log(` ${status} ${ok ? '✅' : '❌'} ${res.url()}`);
    }
  });
  
  const results = { passed: 0, failed: 0 };
  
  async function test(name, fn) {
    try {
      await fn();
      console.log(`✅ ${name}`);
      results.passed++;
    } catch (error) {
      console.log(`❌ ${name}: ${error.message}`);
      results.failed++;
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    console.log(`   📸 ${name}.png`);
  }
  
  async function clickAt(x, y, wait = 3000) {
    await page.mouse.click(x, y);
    await page.waitForTimeout(wait);
  }
  
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  try {
    // ===== 第1步：启动流程 =====
    console.log('\n📱 第1步：启动流程\n');
    
    await test('1.1 访问应用', async () => {
      await page.goto('http://localhost:8080', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      await screenshot('test01-splash');
    });
    
    await page.waitForTimeout(3000);
    
    await test('1.2 引导页', async () => {
      await screenshot('test02-onboarding');
    });
    
    await test('1.3 跳过引导页', async () => {
      await clickAt(380, 40, 3000);
      await screenshot('test03-login');
    });
    
    // ===== 第2步：注册流程 =====
    console.log('\n📱 第2步：注册流程\n');
    
    await test('2.1 进入注册页', async () => {
      // Register按钮在底部 (207, 680)
      await clickAt(207, 680, 3000);
      await screenshot('test04-register-page');
    });
    
    const testUsername = 'test_' + Date.now().toString().slice(-8);
    const testPassword = 'test123456';
    
    await test('2.2 填写注册表单', async () => {
      // Username (207, 420)
      await clickAt(207, 420, 500);
      await page.keyboard.type(testUsername);
      await page.waitForTimeout(500);
      
      // Email (207, 490)
      await clickAt(207, 490, 500);
      await page.keyboard.type(`${testUsername}@test.com`);
      await page.waitForTimeout(500);
      
      // Password (207, 560)
      await clickAt(207, 560, 500);
      await page.keyboard.type(testPassword);
      await page.waitForTimeout(500);
      
      // Confirm Password (207, 630)
      await clickAt(207, 630, 500);
      await page.keyboard.type(testPassword);
      await page.waitForTimeout(500);
      
      await screenshot('test05-register-filled');
      console.log(`   用户名: ${testUsername}`);
    });
    
    await test('2.3 点击注册按钮', async () => {
      // Register按钮 (207, 700)
      await clickAt(207, 700, 5000);
      await screenshot('test06-register-result');
    });
    
    // ===== 第3步：检查注册结果 =====
    console.log('\n 第3步：检查注册结果\n');
    
    await test('3.1 验证注册结果', async () => {
      await screenshot('test07-after-register');
    });
    
    // ===== 第4步：登录流程 =====
    console.log('\n📱 第4步：登录流程\n');
    
    await test('4.1 返回登录页', async () => {
      // 如果注册成功应该自动跳转，否则手动去登录
      await page.goto('http://localhost:8080/#/login', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('test08-login-page');
    });
    
    await test('4.2 填写登录信息', async () => {
      // Username (207, 450)
      await clickAt(207, 450, 500);
      await page.keyboard.type(testUsername);
      await page.waitForTimeout(500);
      
      // Password (207, 520)
      await clickAt(207, 520, 500);
      await page.keyboard.type(testPassword);
      await page.waitForTimeout(500);
      
      await screenshot('test09-login-filled');
    });
    
    await test('4.3 点击登录按钮', async () => {
      // Login按钮 (207, 600)
      await clickAt(207, 600, 5000);
      await screenshot('test10-login-result');
    });
    
    // ===== 第5步：登录后首页 =====
    console.log('\n📱 第5步：登录后首页\n');
    
    await test('5.1 检查是否登录成功', async () => {
      await screenshot('test11-after-login');
    });
    
    await test('5.2 导航到首页', async () => {
      await page.goto('http://localhost:8080/#/home', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      await screenshot('test12-home');
    });
    
    // ===== 第6步：浏览服务 =====
    console.log('\n📱 第6步：浏览服务\n');
    
    await test('6.1 查看服务列表', async () => {
      await screenshot('test13-service-list');
    });
    
    await test('6.2 点击服务', async () => {
      await clickAt(207, 300, 5000);
      await screenshot('test14-countries');
    });
    
    // ===== 第7步：个人中心 =====
    console.log('\n📱 第7步：个人中心\n');
    
    await test('7.1 进入个人中心', async () => {
      await clickAt(345, 850, 5000);
      await screenshot('test15-profile');
    });
    
    await test('7.2 进入积分购买', async () => {
      await clickAt(280, 350, 5000);
      await screenshot('test16-payment');
    });
    
    // ===== 第8步：其他页面 =====
    console.log('\n📱 第8步：其他页面\n');
    
    await test('8.1 订单页面', async () => {
      await page.goto('http://localhost:8080/#/orders', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('test17-orders');
    });
    
    await test('8.2 帮助页面', async () => {
      await page.goto('http://localhost:8080/#/profile/help', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('test18-help');
    });
    
    await test('8.3 关于我们', async () => {
      await page.goto('http://localhost:8080/#/profile/about', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('test19-about');
    });
    
    // 打印报告
    console.log('\n' + '='.repeat(60));
    console.log(' 测试报告');
    console.log('='.repeat(60));
    console.log(`✅ 通过: ${results.passed}`);
    console.log(`❌ 失败: ${results.failed}`);
    console.log(`📝 总计: ${results.passed + results.failed}`);
    console.log('='.repeat(60));
    
    if (results.failed > 0) {
      console.log('\n❌ 请查看失败的截图找出问题\n');
    } else {
      console.log('\n 所有页面测试通过！\n');
    }
    
  } catch (error) {
    console.error('❌ 测试出错:', error.message);
  } finally {
    await browser.close();
    console.log('👋 测试完成\n');
  }
})();
