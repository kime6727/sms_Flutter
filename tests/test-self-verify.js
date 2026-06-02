const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🔍 Self-Test: 验证首页滚动和国家列表修复\n');
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
  
  // Navigate to app
  console.log('正在加载应用...');
  await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  
  console.log('📸 1. 启动页');
  await page.screenshot({ path: 'screenshots/self-splash.png', fullPage: true });
  
  // Skip onboarding - click top right
  console.log('跳过引导页...');
  await page.mouse.click(380, 40);
  await page.waitForTimeout(3000);
  
  console.log('📸 2. 登录页');
  await page.screenshot({ path: 'screenshots/self-login.png', fullPage: true });
  
  // Go to register - click the text link at bottom
  console.log('进入注册页...');
  await page.mouse.click(207, 820);
  await page.waitForTimeout(3000);
  
  console.log('📸 3. 注册页');
  await page.screenshot({ path: 'screenshots/self-register.png', fullPage: true });
  
  // Fill form
  const testEmail = `self_${Date.now()}@test.com`;
  console.log(`注册邮箱: ${testEmail}`);
  
  console.log('填写注册信息...');
  await page.mouse.click(207, 450);
  await page.waitForTimeout(500);
  await page.keyboard.type(testEmail);
  await page.waitForTimeout(500);
  
  await page.mouse.click(207, 520);
  await page.waitForTimeout(500);
  await page.keyboard.type('test123456');
  await page.waitForTimeout(500);
  
  console.log('📸 4. 填写完成');
  await page.screenshot({ path: 'screenshots/self-filled.png', fullPage: true });
  
  // Submit
  console.log('提交注册...');
  await page.mouse.click(207, 620);
  
  // Wait for registration and redirect
  console.log('等待注册和跳转...');
  await page.waitForTimeout(20000);
  
  console.log('📸 5. 注册后页面');
  await page.screenshot({ path: 'screenshots/self-after-register.png', fullPage: true });
  
  // Scroll down on home page if services are visible
  console.log('尝试滚动首页...');
  await page.mouse.wheel(0, 400);
  await page.waitForTimeout(2000);
  
  console.log('📸 6. 滚动后首页');
  await page.screenshot({ path: 'screenshots/self-home-scroll.png', fullPage: true });
  
  // Try clicking first service
  console.log('点击第一个服务...');
  await page.mouse.click(80, 300);
  await page.waitForTimeout(10000);
  
  console.log('📸 7. 国家列表');
  await page.screenshot({ path: 'screenshots/self-countries.png', fullPage: true });
  
  console.log('\n' + '='.repeat(60));
  console.log('✅ 截图完成！请检查：');
  console.log('   - self-after-register.png: 是否跳转到首页');
  console.log('   - self-home-scroll.png: 首页服务列表是否可滚动');
  console.log('   - self-countries.png: 国家列表是否有名称和国旗');
  console.log('='.repeat(60));
  
  await browser.close();
})();
