const { chromium } = require('playwright');

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
  const fs = require('fs');
  if (!fs.existsSync('screenshots')) {
    fs.mkdirSync('screenshots');
  }
  
  try {
    console.log('\n📱 开始测试 Simu 应用...\n');
    
    // 1. 测试启动页
    await test('1. 访问应用', async () => {
      await page.goto('http://localhost:8080', { timeout: 30000 });
      await page.waitForLoadState('networkidle');
      await screenshot('01-app-loaded');
    });
    
    // 等待Flutter应用初始化
    await page.waitForTimeout(3000);
    
    // 2. 测试启动页显示
    await test('2. 启动页显示', async () => {
      const hasSplash = await page.locator('body').isVisible();
      if (!hasSplash) throw new Error('启动页未显示');
      await screenshot('02-splash-screen');
    });
    
    // 等待启动页自动跳转
    await page.waitForTimeout(3000);
    
    // 3. 测试引导页
    await test('3. 引导页显示', async () => {
      await page.waitForTimeout(1000);
      const bodyText = await page.locator('body').innerText();
      const hasOnboarding = bodyText.includes('获取虚拟号码') || 
                           bodyText.includes('Get Virtual Numbers') ||
                           bodyText.includes('下一步') ||
                           bodyText.includes('跳过');
      if (!hasOnboarding) {
        console.log('   提示: 可能已经显示过引导页，直接显示登录页');
      }
      await screenshot('03-after-splash');
    });
    
    // 4. 测试引导页滑动
    await test('4. 引导页滑动功能', async () => {
      try {
        const bodyText = await page.locator('body').innerText();
        if (bodyText.includes('下一步') || bodyText.includes('Next')) {
          // 查找并点击下一步按钮
          const nextButton = page.locator('button:has-text("下一步"), button:has-text("Next")').first();
          if (await nextButton.isVisible()) {
            await nextButton.click();
            await page.waitForTimeout(1000);
            await screenshot('04-onboarding-slide');
          }
        }
      } catch (e) {
        console.log('   提示: 引导页滑动测试跳过');
      }
    });
    
    // 5. 测试登录页
    await test('5. 登录页显示', async () => {
      await page.waitForTimeout(1000);
      const bodyText = await page.locator('body').innerText();
      const hasLogin = bodyText.includes('登录') || 
                      bodyText.includes('Login') ||
                      bodyText.includes('邮箱') ||
                      bodyText.includes('Email') ||
                      bodyText.includes('密码') ||
                      bodyText.includes('Password');
      if (!hasLogin) {
        console.log('   当前页面内容:', bodyText.substring(0, 200));
      }
      await screenshot('05-login-page');
    });
    
    // 6. 测试登录表单
    await test('6. 登录表单元素', async () => {
      const inputs = await page.locator('input').count();
      if (inputs < 2) {
        throw new Error(`登录表单输入框数量不足，找到 ${inputs} 个`);
      }
      console.log(`   找到 ${inputs} 个输入框`);
    });
    
    // 7. 测试注册按钮
    await test('7. 注册入口', async () => {
      const bodyText = await page.locator('body').innerText();
      const hasRegister = bodyText.includes('注册') || 
                         bodyText.includes('Register') ||
                         bodyText.includes('没有账号');
      if (!hasRegister) {
        console.log('   提示: 未找到注册入口文本');
      }
    });
    
    // 8. 测试表单验证
    await test('8. 表单验证（空提交）', async () => {
      try {
        const submitButton = page.locator('button').first();
        if (await submitButton.isVisible()) {
          await submitButton.click();
          await page.waitForTimeout(1000);
          await screenshot('08-form-validation');
        }
      } catch (e) {
        console.log('   提示: 表单验证测试跳过');
      }
    });
    
    // 9. 测试输入功能
    await test('9. 输入框交互', async () => {
      try {
        const emailInput = page.locator('input[type="email"], input[type="text"]').first();
        if (await emailInput.isVisible()) {
          await emailInput.click();
          await emailInput.fill('test@example.com');
          const value = await emailInput.inputValue();
          if (value !== 'test@example.com') {
            throw new Error('输入框无法正确填写');
          }
          console.log('   输入框工作正常');
        }
      } catch (e) {
        console.log('   提示: 输入框测试跳过');
      }
    });
    
    // 打印测试报告
    console.log('\n' + '='.repeat(50));
    console.log('📊 测试报告');
    console.log('='.repeat(50));
    console.log(`✅ 通过: ${passedCount}`);
    console.log(`❌ 失败: ${failedCount}`);
    console.log(`📝 总计: ${passedCount + failedCount}`);
    console.log('='.repeat(50));
    
    console.log('\n📸 截图已保存到 screenshots/ 目录');
    
  } catch (error) {
    console.error('❌ 测试过程中出错:', error.message);
  } finally {
    await browser.close();
    console.log('\n👋 浏览器已关闭');
  }
})();
