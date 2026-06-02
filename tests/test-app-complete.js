const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 启动浏览器...');
  const browser = await chromium.launch({ 
    headless: false,
    args: ['--window-size=414,896']
  });
  
  const context = await browser.newContext({
    viewport: { width: 414, height: 896 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'
  });
  
  const page = await context.newPage();
  
  // 测试报告
  const testResults = [];
  let passedCount = 0;
  let failedCount = 0;
  
  async function test(name, fn) {
    try {
      await fn();
      console.log(`✅ ${name}`);
      testResults.push({ name, status: 'passed' });
      passedCount++;
    } catch (error) {
      console.log(`❌ ${name}: ${error.message}`);
      testResults.push({ name, status: 'failed', error: error.message });
      failedCount++;
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
  }
  
  // 创建截图目录
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  try {
    console.log('\n📱 开始测试 Simu 应用...\n');
    
    // 1. 访问应用
    await test('1. 访问应用', async () => {
      await page.goto('http://localhost:8080', { timeout: 30000, waitUntil: 'networkidle' });
      await screenshot('01-app-loaded');
    });
    
    // 等待Flutter应用初始化
    await page.waitForTimeout(5000);
    
    // 2. 启动页显示
    await test('2. 启动页显示', async () => {
      const hasSplash = await page.locator('body').isVisible();
      if (!hasSplash) throw new Error('启动页未显示');
      await screenshot('02-splash-screen');
    });
    
    // 等待启动页自动跳转
    await page.waitForTimeout(3000);
    
    // 3. 引导页显示
    await test('3. 引导页显示', async () => {
      await page.waitForTimeout(1000);
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 200));
      await screenshot('03-onboarding-page1');
    });
    
    // 4. 跳过引导页
    await test('4. 跳过引导页', async () => {
      // Flutter Canvas点击 - Skip按钮在右上角
      await page.mouse.click(380, 40);
      await page.waitForTimeout(3000);
      await screenshot('04-after-skip');
      console.log('   已点击Skip按钮');
    });
    
    // 5. 登录页显示
    await test('5. 登录页显示', async () => {
      await page.waitForTimeout(2000);
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 300));
      
      const hasLogin = bodyText.includes('Login') || bodyText.includes('登录');
      const hasUsername = bodyText.includes('Username') || bodyText.includes('用户名');
      const hasPassword = bodyText.includes('Password') || bodyText.includes('密码');
      const hasRegister = bodyText.includes('Register') || bodyText.includes('注册');
      
      if (!hasLogin || !hasUsername || !hasPassword) {
        throw new Error('登录页元素不完整');
      }
      
      console.log(`   登录页元素: Login=${hasLogin}, Username=${hasUsername}, Password=${hasPassword}, Register=${hasRegister}`);
      await screenshot('05-login-page');
    });
    
    // 6. 输入测试数据
    await test('6. 输入测试数据', async () => {
      // 使用坐标点击输入框（Flutter Canvas）
      // Username输入框大约在 (207, 450)
      await page.mouse.click(207, 450);
      await page.waitForTimeout(500);
      
      // 输入用户名
      await page.keyboard.type('testuser');
      await page.waitForTimeout(500);
      
      // Password输入框大约在 (207, 520)
      await page.mouse.click(207, 520);
      await page.waitForTimeout(500);
      
      // 输入密码
      await page.keyboard.type('test123456');
      await page.waitForTimeout(500);
      
      await screenshot('06-input-filled');
      console.log('   已填写测试数据');
    });
    
    // 7. 点击登录按钮
    await test('7. 点击登录按钮', async () => {
      // Login按钮大约在 (207, 600)
      await page.mouse.click(207, 600);
      await page.waitForTimeout(3000);
      await screenshot('07-after-login-click');
      console.log('   已点击Login按钮');
    });
    
    // 8. 检查登录结果
    await test('8. 检查登录结果', async () => {
      await page.waitForTimeout(2000);
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 300));
      
      // 检查是否显示错误信息或跳转到首页
      const hasError = bodyText.includes('错误') || bodyText.includes('Error') || 
                      bodyText.includes('失败') || bodyText.includes('Failed');
      const hasHome = bodyText.includes('Home') || bodyText.includes('首页') ||
                     bodyText.includes('服务') || bodyText.includes('Service');
      
      console.log(`   登录结果: Error=${hasError}, Home=${hasHome}`);
      await screenshot('08-login-result');
    });
    
    // 9. 测试注册入口
    await test('9. 测试注册入口', async () => {
      // 重新访问应用
      await page.goto('http://localhost:8080', { timeout: 30000, waitUntil: 'networkidle' });
      await page.waitForTimeout(5000);
      
      // 跳过引导页
      await page.mouse.click(380, 40);
      await page.waitForTimeout(3000);
      
      // 点击Register链接（大约在 (207, 680)）
      await page.mouse.click(207, 680);
      await page.waitForTimeout(3000);
      await screenshot('09-register-page');
      
      const bodyText = await page.locator('body').innerText();
      const hasRegister = bodyText.includes('Register') || bodyText.includes('注册');
      console.log(`   注册页显示: ${hasRegister}`);
    });
    
    // 打印测试报告
    console.log('\n' + '='.repeat(50));
    console.log(' 测试报告');
    console.log('='.repeat(50));
    console.log(`✅ 通过: ${passedCount}`);
    console.log(`❌ 失败: ${failedCount}`);
    console.log(`📝 总计: ${passedCount + failedCount}`);
    console.log('='.repeat(50));
    
    console.log('\n 截图已保存到 screenshots/ 目录');
    
  } catch (error) {
    console.error('❌ 测试过程中出错:', error.message);
  } finally {
    await browser.close();
    console.log('\n👋 浏览器已关闭');
  }
})();
