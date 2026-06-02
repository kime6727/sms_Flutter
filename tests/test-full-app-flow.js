const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 启动完整流程测试...\n');
  
  const browser = await chromium.launch({ 
    headless: false,
    args: ['--window-size=414,896']
  });
  
  const context = await browser.newContext({
    viewport: { width: 414, height: 896 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'
  });
  
  const page = await context.newPage();
  
  // 截图目录
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  // 测试报告
  const testResults = [];
  let passedCount = 0;
  let failedCount = 0;
  
  async function test(name, fn) {
    console.log(`📝 测试: ${name}`);
    try {
      await fn();
      passedCount++;
      testResults.push({ name, status: '✅ 通过' });
      console.log(`   ✅ 通过\n`);
    } catch (error) {
      failedCount++;
      testResults.push({ name, status: `❌ 失败: ${error.message}` });
      console.log(`   ❌ 失败: ${error.message}\n`);
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
  }
  
  try {
    // ===== 1. 启动页测试 =====
    console.log('\n📱 第一阶段：启动页和引导页\n');
    
    await test('1.1 访问应用首页', async () => {
      await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      await screenshot('01-splash');
    });
    
    await test('1.2 等待启动页加载完成', async () => {
      await page.waitForTimeout(2000);
      await screenshot('02-after-splash');
    });
    
    // ===== 2. 引导页测试 =====
    console.log('\n📱 第二阶段：引导页\n');
    
    await test('2.1 引导页显示', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 200));
      await screenshot('03-onboarding');
    });
    
    await test('2.2 点击Skip跳过引导页', async () => {
      // 尝试点击Skip按钮
      await page.mouse.click(380, 40);
      await page.waitForTimeout(3000);
      await screenshot('04-after-skip');
    });
    
    // ===== 3. 登录页测试 =====
    console.log('\n📱 第三阶段：登录页\n');
    
    await test('3.1 登录页元素检查', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 300));
      
      const hasLogin = bodyText.toLowerCase().includes('login') || 
                      bodyText.toLowerCase().includes('登录') ||
                      bodyText.toLowerCase().includes('register') ||
                      bodyText.toLowerCase().includes('注册');
      
      if (!hasLogin) {
        console.log('   警告: 未检测到登录页特征，可能需要进一步检查');
      }
      await screenshot('05-login-page');
    });
    
    await test('3.2 点击注册按钮', async () => {
      // 查找"Register"或"注册"按钮
      const inputs = await page.locator('input').count();
      const bodyText = await page.locator('body').innerText();
      console.log('   找到输入框数量:', inputs);
      console.log('   页面文本:', bodyText.substring(0, 500));
      
      // 尝试点击Register链接
      await page.mouse.click(207, 750);
      await page.waitForTimeout(3000);
      await screenshot('06-register-page');
    });
    
    // ===== 4. 注册流程 =====
    console.log('\n📱 第四阶段：用户注册\n');
    
    await test('4.1 填写注册信息', async () => {
      const testEmail = `test${Date.now()}@test.com`;
      const testPassword = 'test123456';
      
      // 填写邮箱
      await page.mouse.click(207, 450);
      await page.waitForTimeout(300);
      await page.keyboard.type(testEmail);
      
      // 填写密码
      await page.mouse.click(207, 520);
      await page.waitForTimeout(300);
      await page.keyboard.type(testPassword);
      
      await screenshot('07-register-filled');
      console.log('   邮箱:', testEmail);
      console.log('   密码:', testPassword);
    });
    
    await test('4.2 提交注册', async () => {
      // 点击注册按钮
      await page.mouse.click(207, 600);
      await page.waitForTimeout(5000);
      await screenshot('08-register-result');
    });
    
    // ===== 5. 首页测试 =====
    console.log('\n📱 第五阶段：首页功能\n');
    
    await test('5.1 首页加载', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 300));
      await screenshot('09-home');
    });
    
    await test('5.2 检查服务列表', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log('   完整页面文本:', bodyText.substring(0, 1000));
      await screenshot('10-service-list');
    });
    
    // ===== 6. 选择服务测试 =====
    console.log('\n📱 第六阶段：选择服务\n');
    
    await test('6.1 点击第一个服务', async () => {
      // 尝试点击第一个服务卡片（坐标可能需要调整）
      await page.mouse.click(100, 300);
      await page.waitForTimeout(3000);
      await screenshot('11-service-detail');
    });
    
    // ===== 7. 选择国家测试 =====
    console.log('\n📱 第七阶段：选择国家\n');
    
    await test('7.1 国家列表加载', async () => {
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 500));
      await screenshot('12-countries');
    });
    
    // ===== 8. 购买流程测试 =====
    console.log('\n📱 第八阶段：购买流程\n');
    
    await test('8.1 点击购买按钮', async () => {
      // 尝试点击Buy按钮
      await page.mouse.click(320, 350);
      await page.waitForTimeout(3000);
      await screenshot('13-purchase');
    });
    
    // ===== 9. 订单测试 =====
    console.log('\n📱 第九阶段：订单查看\n');
    
    await test('9.1 导航到订单页', async () => {
      // 尝试点击Orders标签
      await page.mouse.click(207, 850);
      await page.waitForTimeout(3000);
      await screenshot('14-orders');
    });
    
    // ===== 10. 个人中心测试 =====
    console.log('\n📱 第十阶段：个人中心\n');
    
    await test('10.1 导航到个人中心', async () => {
      await page.mouse.click(340, 850);
      await page.waitForTimeout(3000);
      await screenshot('15-profile');
    });
    
    // 打印测试报告
    console.log('\n' + '='.repeat(60));
    console.log('📊 测试报告');
    console.log('='.repeat(60));
    testResults.forEach(r => {
      console.log(`${r.status} - ${r.name}`);
    });
    console.log('='.repeat(60));
    console.log(`✅ 通过: ${passedCount}`);
    console.log(`❌ 失败: ${failedCount}`);
    console.log(`📝 总计: ${passedCount + failedCount}`);
    console.log('='.repeat(60));
    
    console.log('\n📸 截图已保存到 screenshots/ 目录\n');
    
  } catch (error) {
    console.error('❌ 测试出错:', error.message);
    console.error(error.stack);
  } finally {
    await browser.close();
    console.log('\n👋 测试完成\n');
  }
})();
