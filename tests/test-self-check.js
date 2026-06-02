const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🔍 Self-Test: 检查首页服务列表和国家列表\n');
  
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
  await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  await page.screenshot({ path: 'screenshots/self-splash.png' });
  console.log('📸 1. 启动页');
  
  // Skip onboarding
  await page.mouse.click(380, 40);
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'screenshots/self-after-skip.png' });
  console.log('📸 2. 跳过引导页');
  
  // Go to register - try different positions
  // The register link is at the bottom of the page
  await page.mouse.click(207, 820);
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'screenshots/self-register.png' });
  console.log('📸 3. 注册页面');
  
  // Fill form
  const testEmail = `self_${Date.now()}@test.com`;
  
  // Click email field and type
  await page.mouse.click(207, 450);
  await page.waitForTimeout(500);
  await page.keyboard.type(testEmail);
  await page.waitForTimeout(500);
  
  // Click password field and type
  await page.mouse.click(207, 520);
  await page.waitForTimeout(500);
  await page.keyboard.type('test123456');
  await page.waitForTimeout(500);
  
  await page.screenshot({ path: 'screenshots/self-filled.png' });
  console.log('📸 4. 填写注册信息');
  
  // Submit - try clicking at bottom of form
  await page.mouse.click(207, 620);
  await page.waitForTimeout(15000);
  await page.screenshot({ path: 'screenshots/self-after-submit.png' });
  console.log('📸 5. 提交注册后页面');
  
  // Wait more for potential redirect
  await page.waitForTimeout(5000);
  await page.screenshot({ path: 'screenshots/self-wait-home.png' });
  console.log('📸 6. 等待5秒后的页面');
  
  console.log('\n✅ 截图完成！请检查：');
  console.log('   1. self-after-submit.png - 提交注册后是否跳转到首页');
  console.log('   2. self-wait-home.png - 首页是否显示服务列表');
  
  await browser.close();
})();
