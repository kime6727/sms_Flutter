const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🔍 页面截图验证\n');
  
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
  
  // Navigate directly to login page
  await page.goto('http://localhost:9090/#/login', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  
  console.log('📸 1. 登录页');
  await page.screenshot({ path: 'screenshots/final-login.png', fullPage: true });
  
  // Navigate to home (should redirect to login if not authenticated)
  await page.goto('http://localhost:9090/#/home', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  
  console.log('📸 2. 首页（未登录）');
  await page.screenshot({ path: 'screenshots/final-home-unauth.png', fullPage: true });
  
  // Navigate to profile
  await page.goto('http://localhost:9090/#/profile', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  
  console.log('📸 3. 个人中心（未登录）');
  await page.screenshot({ path: 'screenshots/final-profile-unauth.png', fullPage: true });
  
  // Navigate to splash
  await page.goto('http://localhost:9090/#/', { timeout: 30000, waitUntil: 'networkidle' });
  await page.waitForTimeout(5000);
  
  console.log('📸 4. 启动页');
  await page.screenshot({ path: 'screenshots/final-splash.png', fullPage: true });
  
  console.log('\n✅ 截图完成！');
  
  await browser.close();
})();
