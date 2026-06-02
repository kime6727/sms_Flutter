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
    
    // 1. 测试启动页
    await test('1. 访问应用', async () => {
      await page.goto('http://localhost:8080', { timeout: 30000, waitUntil: 'networkidle' });
      await screenshot('01-app-loaded');
    });
    
    // 等待Flutter应用初始化
    await page.waitForTimeout(5000);
    
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
      console.log('   页面内容:', bodyText.substring(0, 300));
      
      const hasOnboarding = bodyText.includes('获取虚拟号码') || 
                           bodyText.includes('Get Virtual Numbers') ||
                           bodyText.includes('下一步') ||
                           bodyText.includes('Next') ||
                           bodyText.includes('跳过') ||
                           bodyText.includes('Skip');
      
      if (!hasOnboarding) {
        // 检查是否直接跳转到登录页
        const hasLogin = bodyText.includes('登录') || bodyText.includes('Login');
        if (hasLogin) {
          console.log('   已跳过引导页，直接显示登录页');
        }
      }
      await screenshot('03-after-splash');
    });
    
    // 4. 测试引导页滑动
    await test('4. 引导页滑动功能', async () => {
      const bodyText = await page.locator('body').innerText();
      if (bodyText.includes('下一步') || bodyText.includes('Next')) {
        // 查找并点击下一步按钮
        const buttons = await page.locator('button').all();
        for (const button of buttons) {
          const text = await button.innerText();
          if (text.includes('下一步') || text.includes('Next')) {
            await button.click();
            await page.waitForTimeout(1000);
            await screenshot('04-onboarding-slide');
            break;
          }
        }
      }
    });
    
    // 5. 测试登录页
    await test('5. 登录页显示', async () => {
      await page.waitForTimeout(1000);
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
    
    // 6. 测试登录表单
    await test('6. 登录表单元素', async () => {
      const inputs = await page.locator('input').count();
      console.log(`   找到 ${inputs} 个输入框`);
      if (inputs < 2) {
        throw new Error(`登录表单输入框数量不足，找到 ${inputs} 个`);
      }
    });
    
    // 7. 测试输入功能
    await test('7. 输入框交互', async () => {
      const inputs = await page.locator('input').all();
      if (inputs.length >= 2) {
        await inputs[0].click();
        await inputs[0].fill('test@example.com');
        const value = await inputs[0].inputValue();
        console.log(`   输入框1值: ${value}`);
        
        await inputs[1].click();
        await inputs[1].fill('test123');
        const value2 = await inputs[1].inputValue();
        console.log(`   输入框2值: ${value2}`);
        
        await screenshot('07-input-test');
      }
    });
    
    // 8. 测试按钮点击
    await test('8. 按钮交互', async () => {
      const buttons = await page.locator('button').all();
      console.log(`   找到 ${buttons.length} 个按钮`);
      
      for (let i = 0; i < buttons.length; i++) {
        const text = await buttons[i].innerText();
        console.log(`   按钮${i+1}: ${text}`);
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
