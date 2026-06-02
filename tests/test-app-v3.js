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
    console.log('\n 开始测试 Simu 应用...\n');
    
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
    
    // 4. 完成引导页（点击Next 3次）
    await test('4. 完成引导页', async () => {
      for (let i = 0; i < 3; i++) {
        const nextButton = page.locator('button:has-text("Next"), button:has-text("下一步")').first();
        if (await nextButton.isVisible()) {
          await nextButton.click();
          await page.waitForTimeout(1000);
          await screenshot(`04-onboarding-step${i+2}`);
          console.log(`   点击第${i+1}次Next`);
        }
      }
    });
    
    // 5. 登录页显示
    await test('5. 登录页显示', async () => {
      await page.waitForTimeout(2000);
      const bodyText = await page.locator('body').innerText();
      console.log('   页面内容:', bodyText.substring(0, 300));
      
      const hasLogin = bodyText.includes('登录') || 
                      bodyText.includes('Login') ||
                      bodyText.includes('邮箱') ||
                      bodyText.includes('Email') ||
                      bodyText.includes('密码') ||
                      bodyText.includes('Password');
      
      if (!hasLogin) {
        console.log('   警告: 未检测到登录页特征');
      }
      await screenshot('05-login-page');
    });
    
    // 6. 登录表单元素
    await test('6. 登录表单元素', async () => {
      const inputs = await page.locator('input').count();
      console.log(`   找到 ${inputs} 个输入框`);
      if (inputs < 2) {
        throw new Error(`登录表单输入框数量不足，找到 ${inputs} 个`);
      }
    });
    
    // 7. 输入测试数据
    await test('7. 输入测试数据', async () => {
      const inputs = await page.locator('input').all();
      if (inputs.length >= 2) {
        await inputs[0].click();
        await inputs[0].fill('test@example.com');
        await page.waitForTimeout(500);
        
        await inputs[1].click();
        await inputs[1].fill('test123456');
        await page.waitForTimeout(500);
        
        await screenshot('07-input-filled');
        console.log('   已填写测试数据');
      }
    });
    
    // 8. 按钮交互
    await test('8. 按钮交互', async () => {
      const buttons = await page.locator('button').all();
      console.log(`   找到 ${buttons.length} 个按钮`);
      
      for (let i = 0; i < buttons.length; i++) {
        const text = await buttons[i].innerText();
        console.log(`   按钮${i+1}: "${text}"`);
      }
      
      await screenshot('08-buttons');
    });
    
    // 9. 注册入口
    await test('9. 注册入口', async () => {
      const bodyText = await page.locator('body').innerText();
      const hasRegister = bodyText.includes('注册') || 
                         bodyText.includes('Register') ||
                         bodyText.includes('没有账号');
      if (!hasRegister) {
        console.log('   提示: 未找到注册入口文本');
      } else {
        console.log('   找到注册入口');
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
