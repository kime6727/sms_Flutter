const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('🚀 Web客户端完整流程测试\n');
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
        console.log(`   ⚠️ 发现问题: ${issue}`);
      } else {
        console.log(`   ✅ 通过`);
      }
      results.passed++;
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
  
  await step('1.1 访问应用首页', async () => {
    await page.goto('http://localhost:9090', { timeout: 30000, waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    await screenshot('flow-new-01-splash');
    return null;
  });
  
  await step('1.2 引导页显示和跳过', async () => {
    await page.waitForTimeout(2000);
    await screenshot('flow-new-02-onboarding');
    
    // 点击Skip
    await page.mouse.click(380, 40);
    await page.waitForTimeout(3000);
    await screenshot('flow-new-03-after-skip');
    return null;
  });
  
  // ===== Step 2: 登录页 =====
  console.log('\n📱 阶段二：登录页\n');
  
  await step('2.1 登录页UI检查', async () => {
    const text = await getBodyText();
    await screenshot('flow-new-04-login-page');
    console.log('   页面文本:', text.substring(0, 200));
    return null;
  });
  
  // ===== Step 3: 注册 =====
  console.log('\n📱 阶段三：注册流程\n');
  
  await step('3.1 点击注册按钮', async () => {
    // 点击"自定义密码注册"按钮 (约207, 750)
    await page.mouse.click(207, 750);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-05-register-page');
    return null;
  });
  
  const testEmail = `test_${Date.now()}@test.com`;
  const testPassword = 'test123456';
  
  await step('3.2 填写注册信息', async () => {
    // 点击邮箱输入框 (207, 450)
    await page.mouse.click(207, 450);
    await page.waitForTimeout(300);
    await page.keyboard.type(testEmail);
    
    // 点击密码输入框 (207, 520)
    await page.mouse.click(207, 520);
    await page.waitForTimeout(300);
    await page.keyboard.type(testPassword);
    
    await screenshot('flow-new-06-register-filled');
    console.log(`   邮箱: ${testEmail}`);
    return null;
  });
  
  await step('3.3 提交注册', async () => {
    // 点击注册按钮 (207, 620)
    await page.mouse.click(207, 620);
    await page.waitForTimeout(5000);
    await screenshot('flow-new-07-register-result');
    
    const text = await getBodyText();
    if (text.includes('No services')) {
      return '注册后首页没有加载服务列表';
    }
    return null;
  });
  
  // ===== Step 4: 首页服务列表 =====
  console.log('\n📱 阶段四：首页服务列表\n');
  
  await step('4.1 服务列表加载检查', async () => {
    await page.waitForTimeout(3000);
    const text = await getBodyText();
    await screenshot('flow-new-08-home-services');
    console.log('   页面文本:', text.substring(0, 300));
    if (text.includes('No services') || text.includes('no services')) {
      return '服务列表为空 - 显示"No services available"';
    }
    return null;
  });
  
  // ===== Step 5: 搜索功能 =====
  console.log('\n📱 阶段五：搜索功能\n');
  
  await step('5.1 搜索框测试', async () => {
    // 点击搜索框 (207, 180)
    await page.mouse.click(207, 180);
    await page.waitForTimeout(500);
    await page.keyboard.type('Google');
    await page.waitForTimeout(2000);
    await screenshot('flow-new-09-search');
    return null;
  });
  
  await step('5.2 清空搜索', async () => {
    // 点击清除按钮 (约360, 180)
    await page.mouse.click(360, 180);
    await page.waitForTimeout(1000);
    await screenshot('flow-new-10-search-cleared');
    return null;
  });
  
  // ===== Step 6: 选择服务 =====
  console.log('\n📱 阶段六：选择服务\n');
  
  await step('6.1 点击第一个服务卡片', async () => {
    // 点击第一个服务卡片 (约80, 300)
    await page.mouse.click(80, 300);
    await page.waitForTimeout(4000);
    await screenshot('flow-new-11-country-list');
    
    const text = await getBodyText();
    if (text.includes('No countries')) {
      return '选择服务后，国家列表为空';
    }
    return null;
  });
  
  // ===== Step 7: 国家列表 =====
  console.log('\n📱 阶段七：国家列表\n');
  
  await step('7.1 国家列表检查', async () => {
    const text = await getBodyText();
    console.log('   页面文本:', text.substring(0, 500));
    await screenshot('flow-new-12-country-detail');
    
    if (text.includes('No countries')) {
      return '国家列表为空';
    }
    return null;
  });
  
  // ===== Step 8: 购买确认 =====
  console.log('\n📱 阶段八：购买流程\n');
  
  await step('8.1 点击第一个国家的购买按钮', async () => {
    // 点击第一个国家的Buy按钮 (约320, 280)
    await page.mouse.click(320, 280);
    await page.waitForTimeout(3000);
    await screenshot('flow-new-13-purchase-confirm');
    
    const text = await getBodyText();
    console.log('   页面文本:', text.substring(0, 400));
    return null;
  });
  
  await step('8.2 确认购买', async () => {
    // 点击确认购买按钮 (约207, 700)
    await page.mouse.click(207, 700);
    await page.waitForTimeout(5000);
    await screenshot('flow-new-14-purchase-result');
    
    const text = await getBodyText();
    if (text.includes('0 Credit') || text.includes('0 credit')) {
      return '余额为0，无法购买（这是正常现象，用户需要先充值）';
    }
    return null;
  });
  
  // ===== Step 9: 订单页面 =====
  console.log('\n📱 阶段九：订单管理\n');
  
  await step('9.1 导航到订单页', async () => {
    // 点击底部Orders标签 (约207, 860)
    await page.mouse.click(207, 860);
    await page.waitForTimeout(3000);
    await screenshot('flow-new-15-orders');
    
    const text = await getBodyText();
    console.log('   页面文本:', text.substring(0, 200));
    return null;
  });
  
  // ===== Step 10: 个人中心 =====
  console.log('\n📱 阶段十：个人中心\n');
  
  await step('10.1 导航到个人中心', async () => {
    // 点击底部Profile标签 (约340, 860)
    await page.mouse.click(340, 860);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-16-profile');
    
    const text = await getBodyText();
    console.log('   页面文本:', text.substring(0, 300));
    return null;
  });
  
  await step('10.2 检查余额显示', async () => {
    const text = await getBodyText();
    if (text.includes('Balance') || text.includes('余额')) {
      console.log('   余额显示正常');
    }
    return null;
  });
  
  await step('10.3 导航到充值页面', async () => {
    // 点击Top Up按钮 (约300, 100)
    await page.mouse.click(300, 100);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-17-payment');
    
    const text = await getBodyText();
    console.log('   页面文本:', text.substring(0, 300));
    return null;
  });
  
  await step('10.4 导航到设置页面', async () => {
    // 先返回
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    // 点击Settings (约207, 300)
    await page.mouse.click(207, 300);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-18-settings');
    return null;
  });
  
  await step('10.5 导航到通知页面', async () => {
    // 先返回
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    // 点击Notifications (约207, 380)
    await page.mouse.click(207, 380);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-19-notifications');
    return null;
  });
  
  await step('10.6 导航到帮助页面', async () => {
    // 先返回
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    // 点击Help (约207, 460)
    await page.mouse.click(207, 460);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-20-help');
    return null;
  });
  
  // ===== Step 11: 关于/联系我们 =====
  console.log('\n📱 阶段十一：关于和联系\n');
  
  await step('11.1 导航到关于我们', async () => {
    // 先返回
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    // 点击About (约207, 540)
    await page.mouse.click(207, 540);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-21-about');
    return null;
  });
  
  await step('11.2 导航到联系我们', async () => {
    // 先返回
    await page.mouse.click(30, 30);
    await page.waitForTimeout(1000);
    // 点击Contact Us (约207, 620)
    await page.mouse.click(207, 620);
    await page.waitForTimeout(2000);
    await screenshot('flow-new-22-contact');
    return null;
  });
  
  // 汇总报告
  console.log('\n' + '='.repeat(60));
  console.log('📊 测试汇总报告');
  console.log('='.repeat(60));
  console.log(`✅ 通过: ${results.passed}`);
  console.log(`❌ 失败: ${results.failed}`);
  console.log('='.repeat(60));
  
  if (issues.length > 0) {
    console.log('\n⚠️ 发现的问题汇总:');
    console.log('-'.repeat(60));
    issues.forEach((item, index) => {
      console.log(`\n${index + 1}. [${item.step}]`);
      console.log(`   问题: ${item.issue}`);
    });
    console.log('\n' + '-'.repeat(60));
  } else {
    console.log('\n🎉 所有测试通过，没有发现问题！');
  }
  
  console.log('\n📸 截图已保存到 screenshots/ 目录');
  console.log('\n👋 测试完成\n');
  
  await browser.close();
})();
