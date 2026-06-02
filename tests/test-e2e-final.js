const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 完整端到端流程测试\n');
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
  
  // 监听所有网络请求
  page.on('request', request => {
    const url = request.url();
    if (url.includes('localhost:8088/api')) {
      console.log(`📡 请求: ${request.method()} ${url}`);
    }
  });
  
  page.on('response', response => {
    const url = response.url();
    if (url.includes('localhost:8088/api')) {
      const status = response.status();
      console.log(`📥 响应: ${status} ${url.substring(url.lastIndexOf('/') + 1)}`);
    }
  });
  
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
    await page.screenshot({ path: `screenshots/e2e-${name}.png`, fullPage: true });
  }
  
  async function getBodyText() {
    return await page.locator('body').innerText();
  }
  
  // ===== Step 1: 启动页 =====
  console.log('\n 阶段一：启动\n');
  
  await step('1.1 访问应用首页', async () => {
    await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);
    await screenshot('01-splash');
    
    const text = await getBodyText();
    if (text.length < 10) {
      return '启动页内容为空';
    }
    return null;
  });
  
  await step('1.2 跳过引导页', async () => {
    await page.waitForTimeout(2000);
    // 点击右上角Skip
    await page.mouse.click(380, 40);
    await page.waitForTimeout(3000);
    await screenshot('02-after-skip');
    
    const text = await getBodyText();
    if (text.length < 50) {
      return '跳过引导页后页面内容为空';
    }
    return null;
  });
  
  // ===== Step 2: 注册 =====
  console.log('\n📱 阶段二：注册\n');
  
  await step('2.1 点击注册按钮', async () => {
    await page.mouse.click(207, 820);
    await page.waitForTimeout(3000);
    await screenshot('03-register');
    
    const text = await getBodyText();
    if (text.length < 100) {
      return '注册页面未正确显示';
    }
    return null;
  });
  
  const testEmail = `e2e_${Date.now()}@test.com`;
  const testPassword = 'test123456';
  
  await step('2.2 填写注册信息', async () => {
    await page.mouse.click(207, 450);
    await page.waitForTimeout(300);
    await page.keyboard.type(testEmail);
    await page.waitForTimeout(500);
    
    await page.mouse.click(207, 520);
    await page.waitForTimeout(300);
    await page.keyboard.type(testPassword);
    await page.waitForTimeout(500);
    
    await screenshot('04-filled');
    console.log(`   邮箱: ${testEmail}`);
    return null;
  });
  
  await step('2.3 提交注册', async () => {
    await page.mouse.click(207, 620);
    await page.waitForTimeout(15000);
    await screenshot('05-after-register');
    
    const text = await getBodyText();
    if (text.length < 100) {
      return '注册后页面内容为空，可能未成功跳转';
    }
    if (text.includes('Simu') && text.includes('登录') && !text.includes('余额') && !text.includes('订单')) {
      return '注册后仍在登录页面';
    }
    return null;
  });
  
  // ===== Step 3: 首页 =====
  console.log('\n📱 阶段三：首页\n');
  
  await step('3.1 检查首页服务列表', async () => {
    await page.waitForTimeout(3000);
    await screenshot('06-home');
    
    const text = await getBodyText();
    console.log(`   页面文本(前300字符): ${text.substring(0, 300)}`);
    
    if (text.includes('No services') || text.includes('no services')) {
      return '首页显示"No services available"';
    }
    if (text.includes('端点不存在')) {
      return '首页显示"端点不存在"';
    }
    if (text.length < 100) {
      return '首页内容过少，可能加载失败';
    }
    return null;
  });
  
  // ===== Step 4: 搜索 =====
  console.log('\n 阶段四：搜索\n');
  
  await step('4.1 搜索功能', async () => {
    await page.mouse.click(207, 180);
    await page.waitForTimeout(500);
    await page.keyboard.type('Google');
    await page.waitForTimeout(3000);
    await screenshot('07-search');
    return null;
  });
  
  // ===== Step 5: 选择服务 =====
  console.log('\n📱 阶段五：选择服务\n');
  
  await step('5.1 点击第一个服务', async () => {
    await page.mouse.click(80, 300);
    await page.waitForTimeout(10000);
    await screenshot('08-countries');
    
    const text = await getBodyText();
    console.log(`   页面文本(前300字符): ${text.substring(0, 300)}`);
    
    if (text.includes('No countries') || text.includes('no countries')) {
      return '国家列表为空';
    }
    if (text.includes('端点不存在')) {
      return '显示"端点不存在"';
    }
    if (text.length < 100) {
      return '国家列表页面内容过少';
    }
    return null;
  });
  
  // ===== Step 6: 购买 =====
  console.log('\n📱 阶段六：购买\n');
  
  await step('6.1 点击购买按钮', async () => {
    await page.mouse.click(320, 280);
    await page.waitForTimeout(5000);
    await screenshot('09-purchase');
    return null;
  });
  
  // ===== Step 7: 订单 =====
  console.log('\n📱 阶段七：订单\n');
  
  await step('7.1 导航到订单页', async () => {
    await page.mouse.click(207, 860);
    await page.waitForTimeout(5000);
    await screenshot('10-orders');
    
    const text = await getBodyText();
    if (text.includes('端点不存在')) {
      return '订单页显示"端点不存在"';
    }
    return null;
  });
  
  // ===== Step 8: 个人中心 =====
  console.log('\n📱 阶段八：个人中心\n');
  
  await step('8.1 导航到个人中心', async () => {
    await page.mouse.click(340, 860);
    await page.waitForTimeout(3000);
    await screenshot('11-profile');
    
    const text = await getBodyText();
    if (text.includes('端点不存在')) {
      return '个人中心显示"端点不存在"';
    }
    return null;
  });
  
  await step('8.2 设置页面', async () => {
    await page.mouse.click(207, 300);
    await page.waitForTimeout(3000);
    await screenshot('12-settings');
    return null;
  });
  
  await step('8.3 通知页面', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 380);
    await page.waitForTimeout(3000);
    await screenshot('13-notifications');
    return null;
  });
  
  await step('8.4 帮助页面', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 460);
    await page.waitForTimeout(3000);
    await screenshot('14-help');
    return null;
  });
  
  await step('8.5 关于我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 540);
    await page.waitForTimeout(3000);
    await screenshot('15-about');
    return null;
  });
  
  await step('8.6 联系我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 620);
    await page.waitForTimeout(3000);
    await screenshot('16-contact');
    return null;
  });
  
  // 汇总报告
  console.log('\n' + '='.repeat(60));
  console.log('📊 测试汇总报告');
  console.log('='.repeat(60));
  console.log(`✅ 通过: ${results.passed}`);
  console.log(`❌ 失败: ${results.failed}`);
  console.log(`📝 总计: ${results.passed + results.failed}`);
  console.log('='.repeat(60));
  
  if (issues.length > 0) {
    console.log('\n️ 发现的问题汇总:');
    console.log('-'.repeat(60));
    issues.forEach((item, index) => {
      console.log(`\n${index + 1}. [${item.step}]`);
      console.log(`   问题: ${item.issue}`);
    });
    console.log('\n' + '-'.repeat(60));
  } else {
    console.log('\n🎉 所有测试通过！完整流程正常运行！🎉🎉');
  }
  
  console.log('\n 截图已保存到: screenshots/ 目录');
  console.log('   关键截图:');
  console.log('   - e2e-06-home.png (首页)');
  console.log('   - e2e-08-countries.png (国家列表)');
  console.log('   - e2e-10-orders.png (订单页)');
  console.log('   - e2e-11-profile.png (个人中心)');
  console.log('\n👋 测试完成\n');
  
  await browser.close();
})();
