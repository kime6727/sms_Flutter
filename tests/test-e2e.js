const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 Simu 应用完整功能测试\n');
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
  }
  
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  // Helper: check if canvas shows login page
  async function isLoginPage() {
    // Check URL or page title
    const url = page.url();
    return url.includes('login') || url.includes('#/login');
  }
  
  try {
    console.log('='.repeat(50));
    
    // ===== 第一部分：公开页面测试 =====
    console.log('\n📱 第一部分：公开页面测试\n');
    
    await test('1. 启动页 (Splash)', async () => {
      await page.goto('http://localhost:8080/#/splash', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('01-splash');
    });
    
    await test('2. 引导页 (Onboarding)', async () => {
      await page.goto('http://localhost:8080/#/onboarding', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('02-onboarding');
    });
    
    await test('3. 登录页 (Login)', async () => {
      await page.goto('http://localhost:8080/#/login', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('03-login');
    });
    
    await test('4. 注册页 (Register)', async () => {
      await page.goto('http://localhost:8080/#/register', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('04-register');
    });
    
    // ===== 第二部分：模拟登录进入受保护页面 =====
    console.log('\n📱 第二部分：模拟登录状态测试\n');
    
    // 先在localStorage中设置token，然后加载应用
    await test('5. 模拟登录 - 首页 (Home)', async () => {
      // 设置认证token
      await page.goto('http://localhost:8080', { timeout: 30000 });
      await page.waitForTimeout(2000);
      
      // 通过JavaScript设置token到FlutterSecureStorage使用的加密存储
      await page.evaluate(() => {
        localStorage.setItem('flutter.auth_token', 'test-jwt-token-12345');
        localStorage.setItem('flutter.device_id', 'test-device-123');
        localStorage.setItem('flutter.onboarding_completed', 'true');
      });
      
      // 刷新页面让应用读取token
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      
      // 直接导航到首页
      await page.goto('http://localhost:8080/#/home', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('05-home');
    });
    
    await test('6. 订单列表页 (Orders)', async () => {
      await page.goto('http://localhost:8080/#/orders', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('06-orders');
    });
    
    await test('7. 个人中心 (Profile)', async () => {
      await page.goto('http://localhost:8080/#/profile', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('07-profile');
    });
    
    await test('8. 积分购买页 (Payment)', async () => {
      await page.goto('http://localhost:8080/#/profile/payment', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('08-payment');
    });
    
    await test('9. 通知页 (Notifications)', async () => {
      await page.goto('http://localhost:8080/#/profile/notifications', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('09-notifications');
    });
    
    await test('10. 帮助/FAQ页 (Help)', async () => {
      await page.goto('http://localhost:8080/#/profile/help', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('10-help');
    });
    
    await test('11. 联系客服页 (Contact)', async () => {
      await page.goto('http://localhost:8080/#/profile/contact', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('11-contact');
    });
    
    await test('12. 关于我们页 (About)', async () => {
      await page.goto('http://localhost:8080/#/profile/about', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('12-about');
    });
    
    await test('13. 交易记录页 (Transactions)', async () => {
      await page.goto('http://localhost:8080/#/profile/transactions', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('13-transactions');
    });
    
    await test('14. 设置页 (Settings)', async () => {
      await page.goto('http://localhost:8080/#/profile/settings', { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('14-settings');
    });
    
    // ===== 打印报告 =====
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
        console.log(`   • ${t.name}`);
      });
    }
    
    console.log('\n📸 截图已保存到 screenshots/ 目录');
    console.log('   请查看截图确认每个页面的UI是否正确\n');
    
  } catch (error) {
    console.error('❌ 测试出错:', error.message);
  } finally {
    await browser.close();
    console.log('👋 测试完成\n');
  }
})();
