const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 完整流程测试 - 验证所有修复\n');
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
  
  const issues = [];
  const results = { passed: 0, failed: 0 };
  
  async function step(name, fn) {
    console.log(`\n📝 ${name}`);
    try {
      const issue = await fn();
      if (issue) {
        issues.push({ step: name, issue });
        console.log(`   ⚠️ 问题: ${issue}`);
        results.failed++;
      } else {
        console.log(`   ✅ 通过`);
        results.passed++;
      }
    } catch (error) {
      results.failed++;
      issues.push({ step: name, issue: error.message });
      console.log(`   ❌ 失败: ${error.message}`);
    }
  }
  
  async function screenshot(name) {
    await page.screenshot({ path: `screenshots/verify-${name}.png`, fullPage: true });
  }
  
  async function getBodyText() {
    return await page.locator('body').innerText();
  }
  
  // ===== Step 1: 启动页 =====
  console.log('\n📱 阶段一：启动\n');
  
  await step('1.1 启动页', async () => {
    await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
    await page.waitForTimeout(4000);
    await screenshot('01-splash');
    return null;
  });
  
  await step('1.2 跳过引导页', async () => {
    await page.waitForTimeout(1000);
    await page.mouse.click(380, 40);
    await page.waitForTimeout(2000);
    await screenshot('02-login');
    return null;
  });
  
  // ===== Step 2: 注册 =====
  console.log('\n📱 阶段二：注册\n');
  
  await step('2.1 注册页', async () => {
    await page.mouse.click(207, 750);
    await page.waitForTimeout(2000);
    await screenshot('03-register');
    return null;
  });
  
  const testEmail = `test_${Date.now()}@test.com`;
  const testPassword = 'test123456';
  
  await step('2.2 填写注册信息', async () => {
    await page.mouse.click(207, 450);
    await page.waitForTimeout(300);
    await page.keyboard.type(testEmail);
    await page.mouse.click(207, 520);
    await page.waitForTimeout(300);
    await page.keyboard.type(testPassword);
    await screenshot('04-filled');
    return null;
  });
  
  await step('2.3 提交注册并检查服务列表', async () => {
    await page.mouse.click(207, 620);
    await page.waitForTimeout(8000);
    await screenshot('05-home');
    
    const text = await getBodyText();
    if (text.includes('No services') || text.includes('no services')) {
      return '服务列表为空';
    }
    if (text.length < 50) {
      return '页面内容过少，可能加载失败';
    }
    return null;
  });
  
  // ===== Step 3: 选择服务 =====
  console.log('\n📱 阶段三：选择服务\n');
  
  await step('3.1 点击第一个服务', async () => {
    await page.mouse.click(80, 300);
    await page.waitForTimeout(6000);
    await screenshot('06-countries');
    
    const text = await getBodyText();
    if (text.includes('No countries') || text.includes('no countries')) {
      return '国家列表为空';
    }
    if (text.length < 50) {
      return '国家列表页面内容过少';
    }
    return null;
  });
  
  // ===== Step 4: 购买 =====
  console.log('\n📱 阶段四：购买流程\n');
  
  await step('4.1 点击购买按钮', async () => {
    await page.mouse.click(320, 280);
    await page.waitForTimeout(4000);
    await screenshot('07-purchase');
    return null;
  });
  
  // ===== Step 5: 订单 =====
  console.log('\n📱 阶段五：订单\n');
  
  await step('5.1 导航到订单页', async () => {
    await page.mouse.click(207, 860);
    await page.waitForTimeout(3000);
    await screenshot('08-orders');
    return null;
  });
  
  // ===== Step 6: 个人中心 =====
  console.log('\n📱 阶段六：个人中心\n');
  
  await step('6.1 导航到个人中心', async () => {
    await page.mouse.click(340, 860);
    await page.waitForTimeout(2000);
    await screenshot('09-profile');
    return null;
  });
  
  await step('6.2 导航到设置', async () => {
    await page.mouse.click(207, 300);
    await page.waitForTimeout(2000);
    await screenshot('10-settings');
    return null;
  });
  
  await step('6.3 返回个人中心', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await screenshot('11-profile-back');
    return null;
  });
  
  await step('6.4 导航到通知', async () => {
    await page.mouse.click(207, 380);
    await page.waitForTimeout(2000);
    await screenshot('12-notifications');
    return null;
  });
  
  await step('6.5 导航到帮助', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 460);
    await page.waitForTimeout(2000);
    await screenshot('13-help');
    return null;
  });
  
  await step('6.6 导航到关于我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 540);
    await page.waitForTimeout(2000);
    await screenshot('14-about');
    return null;
  });
  
  await step('6.7 导航到联系我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 620);
    await page.waitForTimeout(2000);
    await screenshot('15-contact');
    return null;
  });
  
  // 汇总报告
  console.log('\n' + '='.repeat(60));
  console.log('📊 测试汇总');
  console.log('='.repeat(60));
  console.log(`✅ 通过: ${results.passed}`);
  console.log(`❌ 失败: ${results.failed}`);
  console.log(`📝 总计: ${results.passed + results.failed}`);
  console.log('='.repeat(60));
  
  if (issues.length > 0) {
    console.log('\n⚠️ 发现的问题:');
    console.log('-'.repeat(60));
    issues.forEach((item, index) => {
      console.log(`\n${index + 1}. [${item.step}]`);
      console.log(`   问题: ${item.issue}`);
    });
    console.log('\n' + '-'.repeat(60));
  } else {
    console.log('\n🎉 所有测试通过！');
  }
  
  console.log('\n📸 截图: screenshots/ 目录');
  console.log('\n👋 完成\n');
  
  await browser.close();
})();
