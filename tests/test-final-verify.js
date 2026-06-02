const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🔍 验证修复后的页面效果\n');
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
  await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  
  console.log('📸 1. 启动页');
  await page.screenshot({ path: 'screenshots/final-splash.png' });
  
  // Skip onboarding
  await page.mouse.click(380, 40);
  await page.waitForTimeout(3000);
  
  console.log('📸 2. 跳过引导页');
  await page.screenshot({ path: 'screenshots/final-after-skip.png' });
  
  // Go to register
  await page.mouse.click(207, 820);
  await page.waitForTimeout(3000);
  
  console.log('📸 3. 注册页面');
  await page.screenshot({ path: 'screenshots/final-register.png' });
  
  // Fill form
  const testEmail = `final_${Date.now()}@test.com`;
  
  await page.mouse.click(207, 450);
  await page.waitForTimeout(500);
  await page.keyboard.type(testEmail);
  await page.waitForTimeout(500);
  
  await page.mouse.click(207, 520);
  await page.waitForTimeout(500);
  await page.keyboard.type('test123456');
  await page.waitForTimeout(500);
  
  console.log('📸 4. 填写注册信息');
  await page.screenshot({ path: 'screenshots/final-filled.png' });
  
  // Submit
  await page.mouse.click(207, 620);
  await page.waitForTimeout(15000);
  
  console.log('📸 5. 提交注册后');
  await page.screenshot({ path: 'screenshots/final-after-submit.png' });
  
  // Wait more
  await page.waitForTimeout(5000);
  
  console.log('📸 6. 等待后页面');
  await page.screenshot({ path: 'screenshots/final-wait.png' });
  
  // Click first service
  await page.mouse.click(80, 300);
  await page.waitForTimeout(10000);
  
  console.log('📸 7. 国家列表');
  await page.screenshot({ path: 'screenshots/final-countries.png' });
  
  // Click profile tab
  await page.mouse.click(340, 860);
  await page.waitForTimeout(3000);
  
  console.log('📸 8. 个人中心');
  await page.screenshot({ path: 'screenshots/final-profile.png' });
  
  console.log('\n' + '='.repeat(60));
  console.log('✅ 截图完成！请查看以下截图：');
  console.log('   1. final-after-submit.png - 提交注册后页面');
  console.log('   2. final-wait.png - 等待后的页面');
  console.log('   3. final-countries.png - 国家列表（验证名称和国旗）');
  console.log('   4. final-profile.png - 个人中心（验证余额图标）');
  console.log('='.repeat(60));
  
  await browser.close();
})();
