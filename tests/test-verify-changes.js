const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🔍 验证三项修改\n');
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
  
  // Step 1: 进入应用并注册
  console.log('\n📱 Step 1: 进入应用并注册\n');
  
  await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(4000);
  
  // Skip onboarding
  await page.mouse.click(380, 40);
  await page.waitForTimeout(2000);
  
  // Go to register
  await page.mouse.click(207, 820);
  await page.waitForTimeout(2000);
  
  // Fill form
  const testEmail = `verify_${Date.now()}@test.com`;
  await page.mouse.click(207, 450);
  await page.waitForTimeout(300);
  await page.keyboard.type(testEmail);
  
  await page.mouse.click(207, 520);
  await page.waitForTimeout(300);
  await page.keyboard.type('test123456');
  
  await page.waitForTimeout(500);
  await page.mouse.click(207, 620);
  await page.waitForTimeout(12000);
  
  await page.screenshot({ path: 'screenshots/verify-home.png', fullPage: true });
  console.log('📸 截图: verify-home.png');
  
  // Step 2: 点击第一个服务，查看国家列表（验证修改1）
  console.log('\n📱 Step 2: 查看国家列表\n');
  
  await page.mouse.click(80, 300);
  await page.waitForTimeout(10000);
  
  await page.screenshot({ path: 'screenshots/verify-countries.png', fullPage: true });
  console.log('📸 截图: verify-countries.png');
  
  // Step 3: 进入个人中心（验证修改2：余额显示）
  console.log('\n📱 Step 3: 查看个人中心余额\n');
  
  await page.mouse.click(340, 860);
  await page.waitForTimeout(3000);
  
  await page.screenshot({ path: 'screenshots/verify-profile.png', fullPage: true });
  console.log('📸 截图: verify-profile.png');
  
  console.log('\n' + '='.repeat(60));
  console.log('✅ 验证完成！请查看以下截图：');
  console.log('   1. verify-home.png - 首页（jifen图标）');
  console.log('   2. verify-countries.png - 国家列表（单列布局+模拟号码）');
  console.log('   3. verify-profile.png - 个人中心（jifen图标+余额）');
  console.log('='.repeat(60));
  
  await browser.close();
})();
