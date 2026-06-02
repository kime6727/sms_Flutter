const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 手动流程测试 - 精确点击\n');
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
    await page.screenshot({ path: `screenshots/manual-${name}.png`, fullPage: true });
  }
  
  async function getBodyText() {
    return await page.locator('body').innerText();
  }
  
  // ===== Step 1: 启动 =====
  console.log('\n📱 阶段一：启动\n');
  
  await step('1.1 访问应用', async () => {
    await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
    await page.waitForTimeout(4000);
    await screenshot('01-splash');
    return null;
  });
  
  await step('1.2 跳过引导页', async () => {
    await page.waitForTimeout(1000);
    // Skip按钮在右上角
    await page.mouse.click(380, 40);
    await page.waitForTimeout(2000);
    await screenshot('02-login');
    return null;
  });
  
  // ===== Step 2: 注册 =====
  console.log('\n📱 阶段二：注册\n');
  
  await step('2.1 点击注册按钮', async () => {
    // 注册按钮在底部，"自定义密码注册"文字链接
    await page.mouse.click(207, 820);
    await page.waitForTimeout(2000);
    await screenshot('03-register');
    
    const text = await getBodyText();
    if (text.includes('Simu') && text.includes('登录')) {
      return '注册页面未正确显示，仍然在登录页面';
    }
    return null;
  });
  
  const testEmail = `test_${Date.now()}@test.com`;
  const testPassword = 'test123456';
  
  await step('2.2 填写注册信息', async () => {
    // 邮箱输入框 - 在页面中部
    await page.mouse.click(207, 450);
    await page.waitForTimeout(300);
    await page.keyboard.type(testEmail);
    await page.waitForTimeout(500);
    
    // 密码输入框 - 邮箱下方
    await page.mouse.click(207, 520);
    await page.waitForTimeout(300);
    await page.keyboard.type(testPassword);
    await page.waitForTimeout(500);
    
    await screenshot('04-filled');
    console.log(`   邮箱: ${testEmail}`);
    return null;
  });
  
  await step('2.3 提交注册', async () => {
    // 注册按钮 - 页面底部，表单内的ElevatedButton
    await page.mouse.click(207, 620);
    await page.waitForTimeout(10000);
    await screenshot('05-after-register');
    
    const text = await getBodyText();
    if (text.includes('Simu') && text.includes('登录') && !text.includes('余额') && !text.includes('服务')) {
      return '注册后仍在登录页面，未跳转到首页';
    }
    if (text.includes('No services')) {
      return '首页显示"No services available"';
    }
    return null;
  });
  
  // ===== Step 3: 首页 =====
  console.log('\n📱 阶段三：首页\n');
  
  await step('3.1 检查首页内容', async () => {
    await screenshot('06-home');
    const text = await getBodyText();
    console.log(`   文本: ${text.substring(0, 200)}`);
    
    if (text.length < 100) {
      return '首页内容过少，可能加载失败';
    }
    return null;
  });
  
  // ===== Step 4: 选择服务 =====
  console.log('\n📱 阶段四：选择服务\n');
  
  await step('4.1 点击第一个服务卡片', async () => {
    // 点击第一个服务卡片 - 在列表顶部
    await page.mouse.click(80, 300);
    await page.waitForTimeout(8000);
    await screenshot('07-countries');
    
    const text = await getBodyText();
    if (text.includes('No countries')) {
      return '国家列表为空';
    }
    if (text.length < 100) {
      return '国家列表页面内容过少';
    }
    return null;
  });
  
  // ===== Step 5: 购买 =====
  console.log('\n📱 阶段五：购买\n');
  
  await step('5.1 点击购买', async () => {
    await page.mouse.click(320, 280);
    await page.waitForTimeout(4000);
    await screenshot('08-purchase');
    return null;
  });
  
  // ===== Step 6: 订单 =====
  console.log('\n📱 阶段六：订单\n');
  
  await step('6.1 导航到订单页', async () => {
    await page.mouse.click(207, 860);
    await page.waitForTimeout(3000);
    await screenshot('09-orders');
    return null;
  });
  
  // ===== Step 7: 个人中心 =====
  console.log('\n📱 阶段七：个人中心\n');
  
  await step('7.1 导航到个人中心', async () => {
    await page.mouse.click(340, 860);
    await page.waitForTimeout(2000);
    await screenshot('10-profile');
    return null;
  });
  
  await step('7.2 设置页面', async () => {
    await page.mouse.click(207, 300);
    await page.waitForTimeout(2000);
    await screenshot('11-settings');
    return null;
  });
  
  await step('7.3 通知页面', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 380);
    await page.waitForTimeout(2000);
    await screenshot('12-notifications');
    return null;
  });
  
  await step('7.4 帮助页面', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 460);
    await page.waitForTimeout(2000);
    await screenshot('13-help');
    return null;
  });
  
  await step('7.5 关于我们', async () => {
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    await page.mouse.click(207, 540);
    await page.waitForTimeout(2000);
    await screenshot('14-about');
    return null;
  });
  
  await step('7.6 联系我们', async () => {
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
  console.log(` 总计: ${results.passed + results.failed}`);
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
  
  console.log('\n 截图: screenshots/ 目录');
  console.log('\n👋 完成\n');
  
  await browser.close();
})();
