/**
 * Замер скорости важных страниц/API на prognos9ys.ru (под пользователем).
 *   node local/tools/benchmark_prod_pages.mjs messi@prognos9ys.ru PASS
 */

const BASE = 'https://prognos9ys.ru';
const WC_EVENT = '63849';
const WC_MATCH = '22';
const RUNS = 3;

const [mail, pass] = process.argv.slice(2);
if (!mail || !pass) {
  console.error('Usage: node benchmark_prod_pages.mjs <email> <password>');
  process.exit(1);
}

async function timed(label, fn) {
  const t0 = performance.now();
  const result = await fn();
  const ms = Math.round(performance.now() - t0);
  return { label, ms, result };
}

async function postForm(url, fields) {
  const body = new URLSearchParams(fields);
  const res = await fetch(url, {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body,
  });
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    data = { _raw: text.slice(0, 200), _status: res.status };
  }
  return { status: res.status, data, size: text.length };
}

async function bitrixAction(action, data = {}, token = '') {
  const params = new URLSearchParams({ action, mode: 'class' });
  if (token) params.set('token', token);
  const res = await fetch(`${BASE}/bitrix/services/main/ajax.php?${params}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(data),
  });
  const text = await res.text();
  let payload;
  try {
    payload = JSON.parse(text);
  } catch {
    payload = { status: 'error', _raw: text.slice(0, 200) };
  }
  return { status: res.status, payload, size: text.length };
}

async function getAsset(path) {
  const res = await fetch(`${BASE}${path}`, { method: 'GET' });
  const buf = await res.arrayBuffer();
  return { status: res.status, size: buf.byteLength };
}

function avg(arr) {
  return Math.round(arr.reduce((a, b) => a + b, 0) / arr.length);
}

async function measureRepeated(label, fn) {
  const times = [];
  let last = null;
  for (let i = 0; i < RUNS; i++) {
    const { ms, result } = await timed(label, fn);
    times.push(ms);
    last = result;
  }
  return { label, avg: avg(times), min: Math.min(...times), max: Math.max(...times), last };
}

async function main() {
  console.log(`Benchmark prod: ${BASE} (${RUNS} runs per API)\n`);

  const assets = [
    '/mob_app/index.html',
    '/mob_app/js/chunk-vendors.c3ec3e37.js',
    '/mob_app/js/app.3f6cd2db.js',
    '/mob_app/css/chunk-vendors.3b0641a8.css',
    '/mob_app/css/app.dce955b1.css',
  ];

  console.log('=== Static assets (1 run) ===');
  for (const path of assets) {
    const { ms, result } = await timed(path, () => getAsset(path));
    const kb = Math.round(result.size / 1024);
    console.log(`${String(ms).padStart(5)} ms  ${String(kb).padStart(6)} KB  ${path}`);
  }

  console.log('\n=== Auth ===');
  const login = await measureRepeated('login (mail+pass)', () =>
    postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'newLogin', mail, pass })
  );
  const token = login.last?.data?.info?.UF_TOKEN;
  console.log(`${login.label}: avg ${login.avg} ms (min ${login.min}, max ${login.max}) status=${login.last?.data?.status}`);
  if (!token) {
    console.error('Login failed:', login.last);
    process.exit(1);
  }

  const tokenLogin = await measureRepeated('tokenLogin (checkAuth)', () =>
    postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token })
  );
  console.log(`${tokenLogin.label}: avg ${tokenLogin.avg} ms`);

  const apis = [
    {
      label: 'catalog /catalog',
      fn: () => bitrixAction('prognos9ys:main.CatalogController.getEvents', { type: 'catalog' }),
    },
    {
      label: `football event /football/${WC_EVENT}`,
      fn: () => bitrixAction('prognos9ys:main.FootballController.getEventMatches', { events: WC_EVENT, userToken: token }),
    },
    {
      label: `football match /football/${WC_EVENT}/${WC_MATCH}`,
      fn: () => bitrixAction('prognos9ys:main.FootballController.getMatch', { eventId: WC_EVENT, number: WC_MATCH, userToken: token }),
    },
    {
      label: 'game state (header/profile)',
      fn: () => bitrixAction('prognos9ys:main.GameController.getState', { userToken: token }, token),
    },
    {
      label: 'my profile /profile',
      fn: () => bitrixAction('prognos9ys:main.ProfileController.getMyProfile', { userToken: token }, token),
    },
    {
      label: `ratings football event ${WC_EVENT} (selector=all, limit=50)`,
      fn: () => bitrixAction('prognos9ys:main.RatingController.getFootballRatings', { event: WC_EVENT, userToken: token, selector: 'all', limit: 50 }),
    },
    {
      label: 'main page /main (nearest events)',
      fn: () => postForm(`${BASE}/mob_app/ajax/main_page/`, { userToken: token }),
    },
  ];

  console.log('\n=== API (Bitrix actions) ===');
  const rows = [];
  for (const api of apis) {
    const m = await measureRepeated(api.label, api.fn);
    const ok = m.last?.payload?.status === 'success';
    const respKb = Math.round((m.last?.size || 0) / 1024);
    rows.push({ ...m, ok, respKb });
    console.log(
      `${String(m.avg).padStart(5)} ms avg  ${String(respKb).padStart(5)} KB  ${ok ? 'OK' : 'ERR'}  ${api.label}`
    );
  }

  console.log('\n=== Simulated page load (sequential, cold) ===');
  const pages = [
    {
      name: 'Catalog (guest)',
      steps: [
        () => bitrixAction('prognos9ys:main.CatalogController.getEvents', { type: 'catalog' }),
      ],
    },
    {
      name: 'Catalog (logged in)',
      steps: [
        () => postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token }),
        () => bitrixAction('prognos9ys:main.CatalogController.getEvents', { type: 'catalog' }),
      ],
    },
    {
      name: 'Football match #22 (logged in)',
      steps: [
        () => postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token }),
        () => bitrixAction('prognos9ys:main.FootballController.getMatch', { eventId: WC_EVENT, number: WC_MATCH, userToken: token }),
      ],
    },
    {
      name: 'Football event list (logged in)',
      steps: [
        () => postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token }),
        () => bitrixAction('prognos9ys:main.FootballController.getEventMatches', { events: WC_EVENT, userToken: token }),
      ],
    },
    {
      name: 'Main page /main (logged in)',
      steps: [
        () => postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token }),
        () => postForm(`${BASE}/mob_app/ajax/main_page/`, { userToken: token }),
        () => postForm(`${BASE}/mob_app/ajax/humor/one/`, {}),
        () => postForm(`${BASE}/mob_app/ajax/news/one/`, {}),
      ],
    },
    {
      name: 'Profile (logged in)',
      steps: [
        () => postForm(`${BASE}/mob_app/ajax/auth/`, { type: 'tokenLogin', token }),
        () => bitrixAction('prognos9ys:main.GameController.getState', { userToken: token }, token),
        () => bitrixAction('prognos9ys:main.ProfileController.getMyProfile', { userToken: token }, token),
      ],
    },
  ];

  for (const page of pages) {
    const t0 = performance.now();
    for (const step of page.steps) await step();
    const total = Math.round(performance.now() - t0);
    console.log(`${String(total).padStart(5)} ms  ${page.name}`);
  }

  console.log('\nDone.');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
