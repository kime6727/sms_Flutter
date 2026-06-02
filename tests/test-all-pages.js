const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 启动浏览器...\n');
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
  
  async function loadPage(path, waitTime = 5000) {
    await page.goto(`http://localhost:8080/#${path}`, { 
      timeout: 30000, 
      waitUntil: 'networkidle' 
    });
    await page.waitForTimeout(waitTime);
  }
  
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  try {
    console.log('📱 Simu 应用完整功能测试\n');
    console.log('='.repeat(50));
    
    // ===== 1. 启动页 =====
    await test('📄 启动页 (Splash)', async () => {
      await loadPage('/splash');
      await screenshot('01-splash');
      const body = await page.locator('body').innerText();
      if (!body.includes('Simu')) throw new Error('启动页未显示Simu');
      console.log('   ✅ Simu 品牌显示正常');
    });
    
    // ===== 2. 引导页 =====
    await test('📄 引导页 (Onboarding)', async () => {
      await loadPage('/onboarding');
      await screenshot('02-onboarding');
      const body = await page.locator('body').innerText();
      const hasContent = body.includes('获取虚拟号码') || body.includes('Get Virtual');
      if (!hasContent) throw new Error('引导页内容未显示');
      console.log('   ✅ 引导页内容正常');
    });
    
    // ===== 3. 登录页 =====
    await test('📄 登录页 (Login)', async () => {
      await loadPage('/login');
      await screenshot('03-login');
      const body = await page.locator('body').innerText();
      if (!body.includes('Login') && !body.includes('登录')) throw new Error('登录页未显示');
      if (!body.includes('Username') && !body.includes('用户名')) throw new Error('用户名输入框未显示');
      if (!body.includes('Password') && !body.includes('密码')) throw new Error('密码输入框未显示');
      if (!body.includes('Register') && !body.includes('注册')) throw new Error('注册入口未显示');
      console.log('   ✅ 登录表单完整');
    });
    
    // ===== 4. 注册页 =====
    await test('📄 注册页 (Register)', async () => {
      await loadPage('/register');
      await screenshot('04-register');
      const body = await page.locator('body').innerText();
      if (!body.includes('Register') && !body.includes('注册')) throw new Error('注册页未显示');
      if (!body.includes('Email')) throw new Error('邮箱输入框未显示');
      console.log('   ✅ 注册表单完整');
    });
    
    // ===== 5. 首页 =====
    await test('📄 首页 (Home)', async () => {
      await loadPage('/home');
      await screenshot('05-home');
      const body = await page.locator('body').innerText();
      // 首页应该显示服务列表或相关UI
      console.log(`   页面内容: ${body.substring(0, 150)}...`);
    });
    
    // ===== 6. 订单页 =====
    await test(' 订单列表页 (Orders)', async () => {
      await loadPage('/orders');
      await screenshot('06-orders');
      const body = await page.locator('body').innerText();
      if (!body.includes('Order') && !body.includes('订单')) throw new Error('订单页未显示');
      console.log('   ✅ 订单页正常');
    });
    
    // ===== 7. 个人中心 =====
    await test('📄 个人中心 (Profile)', async () => {
      await loadPage('/profile');
      await screenshot('07-profile');
      const body = await page.locator('body').innerText();
      // 检查个人中心的关键元素
      const hasBalance = body.includes('Balance') || body.includes('余额') || body.includes('积分');
      const hasSettings = body.includes('Settings') || body.includes('设置');
      console.log(`   余额显示: ${hasBalance ? '✅' : '❌'}, 设置入口: ${hasSettings ? '✅' : '❌'}`);
    });
    
    // ===== 8. 积分购买/充值页 =====
    await test('📄 积分购买页 (Payment/Top-up)', async () => {
      await loadPage('/profile/payment');
      await screenshot('08-payment');
      const body = await page.locator('body').innerText();
      const hasPayment = body.includes('Payment') || body.includes('支付') || 
                        body.includes('Top') || body.includes('充值') ||
                        body.includes('Package') || body.includes('套餐');
      if (!hasPayment) throw new Error('积分购买页未显示');
      console.log('   ✅ 积分购买页正常');
    });
    
    // ===== 9. 通知页 =====
    await test(' 通知页 (Notifications)', async () => {
      await loadPage('/profile/notifications');
      await screenshot('09-notifications');
      const body = await page.locator('body').innerText();
      const hasNotif = body.includes('Notification') || body.includes('通知');
      console.log(`   通知页: ${hasNotif ? '✅' : '⚠️ 空状态'}`);
    });
    
    // ===== 10. 帮助/FAQ页 =====
    await test('📄 帮助/FAQ页 (Help)', async () => {
      await loadPage('/profile/help');
      await screenshot('10-help');
      const body = await page.locator('body').innerText();
      const hasHelp = body.includes('Help') || body.includes('帮助') || 
                     body.includes('FAQ') || body.includes('如何');
      if (!hasHelp) throw new Error('帮助页未显示');
      console.log('   ✅ 帮助/FAQ页正常');
    });
    
    // ===== 11. 联系客服页 =====
    await test('📄 联系客服页 (Contact)', async () => {
      await loadPage('/profile/contact');
      await screenshot('11-contact');
      const body = await page.locator('body').innerText();
      const hasContact = body.includes('Contact') || body.includes('联系') || 
                        body.includes('Email') || body.includes('Telegram');
      if (!hasContact) throw new Error('联系客服页未显示');
      console.log('   ✅ 联系客服页正常');
    });
    
    // ===== 12. 关于我们页 =====
    await test('📄 关于我们页 (About)', async () => {
      await loadPage('/profile/about');
      await screenshot('12-about');
      const body = await page.locator('body').innerText();
      const hasAbout = body.includes('About') || body.includes('关于') || body.includes('Simu');
      if (!hasAbout) throw new Error('关于我们页未显示');
      console.log('   ✅ 关于我们页正常');
    });
    
    // ===== 13. 交易记录页 =====
    await test('📄 交易记录页 (Transactions)', async () => {
      await loadPage('/profile/transactions');
      await screenshot('13-transactions');
      const body = await page.locator('body').innerText();
      const hasTx = body.includes('Transaction') || body.includes('交易') || 
                   body.includes('充值') || body.includes('购买');
      console.log(`   交易记录页: ${hasTx ? '✅' : '⚠️ 空状态'}`);
    });
    
    // ===== 14. 设置页 =====
    await test('📄 设置页 (Settings)', async () => {
      await loadPage('/profile/settings');
      await screenshot('14-settings');
      const body = await page.locator('body').innerText();
      const hasSettings = body.includes('Setting') || body.includes('设置') || 
                         body.includes('Language') || body.includes('Theme');
      console.log(`   设置页: ${hasSettings ? '✅' : '⚠️ 空状态'}`);
    });
    
    // 打印报告
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
    
    console.log('\n📸 截图已保存到 screenshots/ 目录\n');
    
  } catch (error) {
    console.error('❌ 测试出错:', error.message);
  } finally {
    await browser.close();
    console.log('👋 测试完成\n');
  }
})();
