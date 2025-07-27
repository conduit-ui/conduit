const { chromium } = require('playwright');

(async () => {
  console.log('🧪 Testing containerized Laravel deployment...');
  
  const browser = await chromium.launch({
    headless: false,
    slowMo: 500
  });
  
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Test container server
    console.log('🐳 Testing container server at 13.57.206.160...');
    await page.goto('http://13.57.206.160');
    
    await page.waitForTimeout(2000);
    
    // Check if Laravel loads
    const title = await page.title();
    console.log('📄 Page title:', title);
    
    // Check for Laravel indicators
    const hasLaravel = await page.evaluate(() => {
      return document.body.innerHTML.includes('laravel') || 
             document.body.innerHTML.includes('Laravel') ||
             document.querySelector('meta[name="csrf-token"]') !== null;
    });
    
    if (hasLaravel) {
      console.log('✅ Laravel detected on container server!');
    } else {
      console.log('⚠️ Laravel not detected - might be default nginx');
    }
    
    // Take screenshot
    await page.screenshot({ path: '/Users/jordanpartridge/packages/conduit/playwright-test/container-test.png' });
    console.log('📸 Screenshot saved: container-test.png');
    
    // Test admin login if available
    try {
      await page.goto('http://13.57.206.160/admin');
      await page.waitForTimeout(2000);
      
      if (page.url().includes('/admin')) {
        console.log('🔐 Admin panel accessible on container!');
        await page.screenshot({ path: '/Users/jordanpartridge/packages/conduit/playwright-test/container-admin.png' });
      }
    } catch (e) {
      console.log('❌ Admin panel not accessible yet');
    }
    
  } catch (error) {
    console.log('💥 Container test error:', error.message);
    await page.screenshot({ path: '/Users/jordanpartridge/packages/conduit/playwright-test/container-error.png' });
  }
  
  // Compare with current production
  try {
    console.log('\n🌐 Testing current production server...');
    await page.goto('https://jordanpartridge.us');
    await page.waitForTimeout(2000);
    
    const prodTitle = await page.title();
    console.log('📄 Production title:', prodTitle);
    
    await page.screenshot({ path: '/Users/jordanpartridge/packages/conduit/playwright-test/production-current.png' });
    console.log('📸 Production screenshot saved');
    
  } catch (error) {
    console.log('💥 Production test error:', error.message);
  }
  
  console.log('\n🏁 Container testing complete!');
  console.log('💰 Container cost: ~$3/month vs current $15/month');
  await browser.close();
})();