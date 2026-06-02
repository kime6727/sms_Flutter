const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 完整流程测试 - 连接本地后端\n');
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
  
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  const results = { passed: 0, failed: 0, tests: [] };
  
  async function test(name, fn) {
    console.log(`\n📝 ${name}`);
    try {
      await fn();
      results.passed++;
      results.tests.push({ name, status: '✅ 通过' });
      console.log(`   ✅ 通过`);
    } catch (error) {
      results.failed++;
      results.tests.push({ name, status: `❌ 失败: ${error.message}` });
      console.log(`   ❌ 失败: ${error.message}`);
      throw error;
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    console.log(`   📸 截图: ${name}.png`);
  }
  
  try {
    // ===== Step 1: Splash & Onboarding =====
    console.log('\n📋 Step 1: 启动页和引导页\n');
    
    await test('1.1 访问应用', async () => {
      await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('flow-01-splash');
    });
    
    await test('1.2 跳过引导页', async () => {
      await page.waitForTimeout(2000);
      await page.mouse.click(380, 40);
      await page.waitForTimeout(3000);
      await screenshot('flow-02-after-skip');
    });
    
    // ===== Step 2: Registration =====
    console.log('\n📋 Step 2: 用户注册\n');
    
    await test('2.1 点击Register按钮', async () => {
      await page.mouse.click(207, 750);
      await page.waitForTimeout(2000);
      await screenshot('flow-03-register-page');
    });
    
    const testEmail = `test_${Date.now()}@test.com`;
    const testPassword = 'test123456';
    
    await test('2.2 填写注册信息', async () => {
      await page.mouse.click(207, 450);
      await page.waitForTimeout(300);
      await page.keyboard.type(testEmail);
      
      await page.mouse.click(207, 520);
      await page.waitForTimeout(300);
      await page.keyboard.type(testPassword);
      
      await screenshot('flow-04-filled');
      console.log(`   邮箱: ${testEmail}`);
      console.log(`   密码: ${testPassword}`);
    });
    
    await test('2.3 提交注册', async () => {
      await page.mouse.click(207, 600);
      await page.waitForTimeout(5000);
      await screenshot('flow-05-register-result');
    });
    
    // ===== Step 3: Home Page - Service List =====
    console.log('\n📋 Step 3: 首页 - 服务列表\n');
    
    await test('3.1 首页加载服务列表', async () => {
      await page.waitForTimeout(3000);
      await screenshot('flow-06-home-services');
      
      const bodyText = await page.locator('body').innerText();
      if (bodyText.includes('No services') || bodyText.includes('no services')) {
        throw new Error('服务列表仍然为空！');
      }
      console.log('   服务列表加载成功');
    });
    
    // ===== Step 4: Service Selection =====
    console.log('\n📋 Step 4: 选择服务\n');
    
    await test('4.1 点击第一个服务', async () => {
      await page.mouse.click(100, 300);
      await page.waitForTimeout(3000);
      await screenshot('flow-07-countries');
    });
    
    // ===== Step 5: Country Selection =====
    console.log('\n📋 Step 5: 选择国家\n');
    
    await test('5.1 国家列表加载', async () => {
      const bodyText = await page.locator('body').innerText();
      if (bodyText.includes('No countries') || bodyText.includes('no countries')) {
        throw new Error('国家列表为空！');
      }
      console.log('   国家列表加载成功');
      await screenshot('flow-08-country-detail');
    });
    
    // ===== Step 6: Purchase =====
    console.log('\n📋 Step 6: 购买流程\n');
    
    await test('6.1 点击购买按钮', async () => {
      await page.mouse.click(320, 350);
      await page.waitForTimeout(3000);
      await screenshot('flow-09-purchase-confirm');
    });
    
    await test('6.2 确认购买', async () => {
      await page.mouse.click(207, 750);
      await page.waitForTimeout(5000);
      await screenshot('flow-10-purchase-result');
    });
    
    // ===== Step 7: Order Management =====
    console.log('\n📋 Step 7: 订单管理\n');
    
    await test('7.1 导航到订单页', async () => {
      await page.mouse.click(207, 860);
      await page.waitForTimeout(3000);
      await screenshot('flow-11-orders');
    });
    
    await test('7.2 查看订单详情', async () => {
      await page.mouse.click(207, 300);
      await page.waitForTimeout(3000);
      await screenshot('flow-12-order-detail');
    });
    
    await test('7.3 激活订单', async () => {
      await page.mouse.click(207, 800);
      await page.waitForTimeout(3000);
      await screenshot('flow-13-order-activated');
    });
    
    // ===== Step 8: Profile =====
    console.log('\n📋 Step 8: 个人中心\n');
    
    await test('8.1 导航到个人中心', async () => {
      await page.mouse.click(340, 860);
      await page.waitForTimeout(2000);
      await screenshot('flow-14-profile');
    });
    
    await test('8.2 查看充值页面', async () => {
      await page.mouse.click(300, 200);
      await page.waitForTimeout(2000);
      await screenshot('flow-15-payment');
    });
    
    await test('8.3 返回个人中心', async () => {
      await page.mouse.click(30, 30);
      await page.waitForTimeout(2000);
      await screenshot('flow-16-profile-back');
    });
    
    // Print final report
    console.log('\n' + '='.repeat(60));
    console.log('📊 测试报告');
    console.log('='.repeat(60));
    results.tests.forEach(t => {
      console.log(`${t.status} - ${t.name}`);
    });
    console.log('='.repeat(60));
    console.log(`✅ 通过: ${results.passed}`);
    console.log(`❌ 失败: ${results.failed}`);
    console.log(`📝 总计: ${results.passed + results.failed}`);
    console.log('='.repeat(60));
    
    if (results.failed === 0) {
      console.log('\n🎉🎉 所有测试通过！完整流程运行正常！🎉🎉\n');
    } else {
      console.log(`\n️ 有 ${results.failed} 个测试失败，请检查截图\n`);
    }
    
    console.log('📸 截图已保存到 screenshots/ 目录\n');
    
  } catch (error) {
    console.error('\n❌ 测试出错:', error.message);
    
    console.log('\n' + '='.repeat(60));
    console.log('📊 当前测试报告');
    console.log('='.repeat(60));
    results.tests.forEach(t => {
      console.log(`${t.status} - ${t.name}`);
    });
    console.log('='.repeat(60));
    console.log(`✅ 通过: ${results.passed}`);
    console.log(`❌ 失败: ${results.failed}`);
    console.log(`📝 总计: ${results.passed + results.failed}`);
    console.log('='.repeat(60));
    
  } finally {
    await browser.close();
    console.log('\n👋 测试完成\n');
  }
})();
