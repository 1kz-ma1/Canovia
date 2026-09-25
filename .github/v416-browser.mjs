import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import assert from 'node:assert/strict';

mkdirSync('visual', { recursive: true });
const browser = await chromium.launch();
for (const width of [390, 1440]) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    // Fixture only: no live mutations or navigation to a backend.
    await page.route('**/*', route => route.request().method() === 'GET' ? route.continue() : route.abort());
    await page.goto('http://127.0.0.1:8765/v416.html');
    await page.locator('.pk-v395-guidance-card').first().waitFor();
    await page.screenshot({ path: `visual/home-${width}.png`, fullPage: true });
    const reasons = page.locator('[data-guidance-reasons]').first();
    assert.equal(await reasons.getAttribute('open'), null);
    await reasons.locator('summary').focus();
    await page.keyboard.press('Enter');
    assert.notEqual(await reasons.getAttribute('open'), null);
    await page.keyboard.press('Space');
    assert.equal(await reasons.getAttribute('open'), null);
    await page.locator('[data-dashboard-tab^="plan-"]').first().click();
    const current = page.locator('[data-plan-hub-current]');
    assert.equal(await current.isVisible(), true);
    assert.equal(await current.locator('a.btn-primary').isVisible(), true);
    const detail = page.locator('[data-current-task-details]');
    assert.equal(await detail.getAttribute('open'), null);
    await current.scrollIntoViewIfNeeded();
    await page.screenshot({ path: `visual/hub-${width}.png`, fullPage: true });
    await detail.locator('summary').click();
    assert.notEqual(await detail.getAttribute('open'), null);
    await page.locator('[data-surface-disclosure="study_focus"] > summary').click();
    assert.equal(await page.locator('[data-surface-id="study_focus"]').isVisible(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
    await page.screenshot({ path: `visual/hub-expanded-${width}.png`, fullPage: true });
    await page.close();
}
await browser.close();
console.log('Mobile/desktop disclosure, keyboard, CTA, tab switch and overflow checks passed.');
