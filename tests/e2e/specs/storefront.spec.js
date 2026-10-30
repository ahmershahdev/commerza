// @ts-check
const { test, expect } = require('@playwright/test');

const publicPages = ['/', '/products', '/shop-category-a', '/shop-category-b', '/about', '/contact', '/faq',
  '/shipping', '/returns', '/warranty', '/privacy-policy', '/terms-of-service'];

for (const path of publicPages) {
  test(`renders ${path} without console or CSP errors`, async ({ page }) => {
    const errors = [];
    page.on('console', (msg) => { if (msg.type() === 'error' && !/favicon|res\.cloudinary\.com|net::ERR/i.test(msg.text())) errors.push(msg.text()); });
    page.on('pageerror', (err) => errors.push(err.message));

    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await expect(page.locator('h1')).toHaveCount(1);
    expect((await page.title()).length).toBeLessThanOrEqual(60);
    await expect(page.locator('footer.footer')).toContainText('support@ahmershah.dev');
    expect(errors, errors.join('\n')).toEqual([]);
  });
}

test('product detail exposes Product schema', async ({ page }) => {
  await page.goto('/products/louis-vuitton-escale-automatic-39mm-platinum');
  const ld = await page.locator('script[type="application/ld+json"]').allTextContents();
  expect(ld.join(' ')).toContain('"@type":"Product"');
});

test('guest can add a product to the cart', async ({ page }) => {
  await page.goto('/shop-category-a');
  const badge = page.locator('#cart-count');
  const before = Number(await badge.textContent()) || 0;
  const addButton = page.locator('.product-card button, .product-card a').filter({ hasText: /add to cart/i }).first();
  await addButton.scrollIntoViewIfNeeded();
  await addButton.click();
  await expect.poll(async () => Number(await badge.textContent()) || 0).toBeGreaterThan(before);
  await page.goto('/cart');
  await expect(page.getByText(/order summary/i)).toBeVisible();
});

test('search suggestions respond', async ({ page, isMobile }) => {
  test.skip(isMobile, 'desktop search bar');
  await page.goto('/');
  await page.locator('form[name="product_query"] input[type="search"]').fill('automatic');
  await expect(page.locator('.search-suggestions')).toBeVisible();
});

test('protected routes redirect and internals are blocked', async ({ page, request }) => {
  await page.goto('/account');
  await expect(page).toHaveURL(/login/);
  for (const path of ['/.env', '/database/commerza.sql', '/backend/core/data.php', '/storage/mail/']) {
    const res = await request.get(path);
    expect([403, 404]).toContain(res.status());
  }
});

test('legacy .php URL redirects to clean route', async ({ request }) => {
  const res = await request.get('/about.php', { maxRedirects: 0 });
  expect(res.status()).toBe(301);
  expect(res.headers()['location']).toMatch(/\/about$/);
});
