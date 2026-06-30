/**
 * Скриншоты для инструкции «Как играть».
 * Usage: node local/tools/capture_guide_screenshots.mjs [email] [password]
 */
import { createRequire } from 'module';
import { mkdir } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');
const require = createRequire(import.meta.url);
const { chromium } = require(path.join(root, 'frontend/node_modules/playwright'));
const outDir = path.join(root, 'frontend/public/guide');
const baseUrl = process.env.GUIDE_BASE_URL || 'http://prognos9ys/mob_app';

const email = process.argv[2] || process.env.GUIDE_EMAIL || '';
const password = process.argv[3] || process.env.GUIDE_PASSWORD || '';

const shots = [
  { name: 'nav', path: '/catalog', wait: 1500 },
  { name: 'catalog', path: '/catalog', wait: 1500 },
  { name: 'profile', path: '/profile?tab=economy', wait: 2500 },
  { name: 'inventory', path: '/profile?tab=inventory', wait: 2500 },
  { name: 'exchange', path: '/profile?tab=economy&eco=exchange', wait: 3000 },
];

async function login(page) {
  await page.goto(`${baseUrl}/auth`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.input_wrapper');

  const emailWrapper = page.locator('.input_wrapper').filter({ hasText: 'E-mail' });
  await emailWrapper.click();
  await emailWrapper.locator('input.input').fill(email);

  const passWrapper = page.locator('.input_wrapper').filter({ hasText: 'Пароль' });
  await passWrapper.click();
  await passWrapper.locator('input.input').fill(password);

  await page.getByText('Войти', { exact: true }).click();
  await page.waitForURL((url) => !url.pathname.includes('/auth'), { timeout: 30000 });
  await page.waitForTimeout(1500);
}

async function captureMatch(page) {
  await page.goto(`${baseUrl}/catalog`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  const footballEvent = page.locator('.el_event.football').first();
  if (await footballEvent.count() === 0) {
    console.warn('match: no football event found, skip');
    return;
  }

  await footballEvent.locator('.btn').filter({ hasText: 'Список' }).click();
  await page.waitForURL(/\/football\//, { timeout: 20000 });
  await page.waitForTimeout(2000);

  const matchBtn = page.locator('.match_btn').first();
  if (await matchBtn.count() > 0) {
    await matchBtn.click();
  } else {
    await page.locator('.match_box').first().click();
  }

  await page.waitForURL(/\/football\/[^/]+\/\d+/, { timeout: 20000 });
  await page.waitForTimeout(2500);
  await page.screenshot({ path: path.join(outDir, 'match.png'), fullPage: false });
  console.log('saved match.png');
}

async function main() {
  if (!email || !password) {
    console.error('Usage: node capture_guide_screenshots.mjs <email> <password>');
    process.exit(1);
  }

  await mkdir(outDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 400, height: 780 },
    deviceScaleFactor: 2,
  });
  const page = await context.newPage();

  try {
    await login(page);

    for (const shot of shots) {
      await page.goto(`${baseUrl}${shot.path}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(shot.wait);
      const file = path.join(outDir, `${shot.name}.png`);
      await page.screenshot({ path: file, fullPage: false });
      console.log(`saved ${shot.name}.png`);
    }

    await captureMatch(page);
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
