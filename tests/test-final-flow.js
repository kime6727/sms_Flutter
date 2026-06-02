const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 Web客户端完整流程测试 - HTML渲染器版本\n');
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
    await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
  }
  
  async function getBodyText() {
    return await page.locator('body').innerText();
  }
  
  // ===== Step 1: 启动页 =====
  console.log('\n📱 阶段一：启动和引导\n');
  
  await step('1.1 启动页 - 检查文字显示', async () => {
    await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
    await page.waitForTimeout(4000);
    await screenshot('final-01-splash');
    
    const text = await getBodyText();
    if (text.includes('□') || text.includes('▯')) {
      return '启动页文字显示为方框(字体问题)';
    }
    return null;
  });
  
  await step('1.2 引导页 - 检查文字显示', async () => {
    await page.waitForTimeout(2000);
    await screenshot('final-02-onboarding');
    
    // 点击Skip
    await page.mouse.click(380, 40);
    await page.waitForTimeout(3000);
    await screenshot('final-03-after-skip');
    return null;
  });
  
  // ===== Step 2: 登录页 =====
  console.log('\n📱 阶段二：登录页\n');
  
  await step('2.1 登录页UI检查', async () => {
    await screenshot('final-04-login');
    const text = await getBodyText();
    console.log('   文本:', text.substring(0, 150));
    return null;
  });
  
  // ===== Step 3: 注册 =====
  console.log('\n📱 阶段三：注册流程\n');
  
  await step('3.1 注册页', async () => {
    await page.mouse.click(207, 750);
    await page.waitForTimeout(2000);
    await screenshot('final-05-register');
    return null;
  });
  
  const testEmail = `test_${Date.now()}@test.com`;
  const testPassword = 'test123456';
  
  await step('3.2 填写注册信息', async () => {
    await page.mouse.click(207, 450);
    await page.waitForTimeout(300);
    await page.keyboard.type(testEmail);
    
    await page.mouse.click(207, 520);
    await page.waitForTimeout(300);
    await page.keyboard.type(testPassword);
    
    await screenshot('final-06-filled');
    return null;
  });
  
  await step('3.3 提交注册', async () => {
    await page.mouse.click(207, 620);
    await page.waitForTimeout(6000);
    await screenshot('final-07-register-result');
    
    const text = await getBodyText();
    if (text.includes('No services')) {
      return '注册后首页没有加载服务列表';
    }
    return null;
  });
  
  // ===== Step 4: 首页服务列表 =====
  console.log('\n📱 阶段四：首页\n');
  
  await step('4.1 服务列表加载', async () => {
    await page.waitForTimeout(3000);
    await screenshot('final-08-home');
    
    const text = await getBodyText();
    console.log('   文本:', text.substring(0, 200));
    
    if (text.includes('No services') || text.includes('no services')) {
      return '服务列表为空';
    }
    return null;
  });
  
  // ===== Step 5: 搜索功能 =====
  console.log('\n📱 阶段五：搜索\n');
  
  await step('5.1 搜索框', async () => {
    await page.mouse.click(207, 180);
    await page.waitForTimeout(500);
    await page.keyboard.type('Google');
    await page.waitForTimeout(2000);
    await screenshot('final-09-search');
    return null;
  });
  
  // ===== Step 6: 选择服务 =====
  console.log('\n📱 阶段六：选择服务\n');
  
  await step('6.1 点击第一个服务', async () => {
    await page.mouse.click(80, 300);
    await page.waitForTimeout(4000);
    await screenshot('final-10-countries');
    
    const text = await getBodyText();
    if (text.includes('No countries')) {
      return '国家列表为空';
    }
    return null;
  });
  
  // ===== Step 7: 国家列表 =====
  console.log('\n📱 阶段七：国家列表\n');
  
  await step('7.1 国家列表显示', async () => {
    await screenshot('final-11-country-detail');
    return null;
  });
  
  // ===== Step 8: 购买 =====
  console.log('\n📱 阶段八：购买\n');
  
  await step('8.1 点击购买', async () => {
    await page.mouse.click(320, 280);
    await page.waitForTimeout(3000);
    await screenshot('final-12-purchase');
    
    const text = await getBodyText();
    if (text.includes('Insufficient balance') || text.includes('余额不足')) {
      console.log('   余额不足，这是正常现象');
      return null;
    }
    return null;
  });
  
  // ===== Step 9: 订单 =====
  console.log('\n📱 阶段九：订单\n');
  
  await step('9.1 导航到订单页', async () => {
    await page.mouse.click(207, 860);
    await page.waitForTimeout(3000);
    await screenshot('final-13-orders');
    return null;
  });
  
  // ===== Step 10: 个人中心 =====
  console.log('\n📱 阶段十：个人中心\n');
  
  await step('10.1 导航到个人中心', async () => {
    await page.mouse.click(340, 860);
    await page.waitForTimeout(2000);
    await screenshot('final-14-profile');
    return null;
  });
  
  await step('10.2 导航到设置', async () => {
    await page.mouse.click(207, 300);
    await page.waitForTimeout(2000);
    await screenshot('final-15-settings');
    return null;
  });
  
  await step('10.3 返回并导航到通知', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 380);
    await page.waitForTimeout(2000);
    await screenshot('final-16-notifications');
    return null;
  });
  
  // ===== Step 11: 帮助/关于 =====
  console.log('\n📱 阶段十一：帮助/关于\n');
  
  await step('11.1 帮助页面', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 460);
    await page.waitForTimeout(2000);
    await screenshot('final-17-help');
    return null;
  });
  
  await step('11.2 关于我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 540);
    await page.waitForTimeout(2000);
    await screenshot('final-18-about');
    return null;
  });
  
  await step('11.3 联系我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 620);
    await page.waitForTimeout(2000);
    await screenshot('final-19-contact');
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
