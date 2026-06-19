/**
 * Создание 8 публичных CS2-сборников на бою (обход бага до pull 012cea3).
 *   node local/tools/seed_cs2_rating_sets_live.mjs
 */

const BASE = 'https://prognos9ys.ru';

const TEAMS = [
  { title: 'Team Spirit', userIds: [329, 330, 331, 332, 333, 334] },
  { title: 'FURIA', userIds: [335, 336, 337, 338, 339, 340] },
  { title: 'Aurora', userIds: [341, 342, 343, 344, 345, 346] },
  { title: 'Vitality', userIds: [347, 348, 349, 350, 351, 352] },
  { title: 'Falcons', userIds: [353, 354, 355, 356, 357, 358] },
  { title: 'BetBoom', userIds: [359, 360, 361, 362, 363, 364] },
  { title: '9z', userIds: [376, 365, 366, 367, 368, 369] },
  { title: 'G2', userIds: [370, 371, 372, 373, 374, 375] },
];

async function bitrixAction(action, data = {}) {
  const url = `${BASE}/bitrix/services/main/ajax.php?action=${encodeURIComponent(action)}&mode=class`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(data),
  });
  const json = await res.json();
  if (json.status !== 'success') {
    throw new Error(json.errors?.[0]?.message || JSON.stringify(json));
  }
  return json.data;
}

async function login() {
  const body = new URLSearchParams({
    type: 'newLogin',
    mail: 'donk@prognos9ys.ru',
    pass: 'donk26',
  });
  const res = await fetch(`${BASE}/mob_app/ajax/auth/`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  });
  const json = await res.json();
  if (json.status !== 'ok' || !json.info?.UF_TOKEN) {
    throw new Error(json.mes || 'login failed');
  }
  return json.info.UF_TOKEN;
}

async function resolveEventId() {
  const data = await bitrixAction('prognos9ys:main.CatalogController.getEvents', { type: 'cs2' });
  const result = data?.result || data?.getEvents?.result || {};
  for (const group of Object.values(result)) {
    const code = group?.info?.CODE || group?.info?.code;
    if (code !== 'cs2') continue;
    for (const bucket of Object.values(group.events || {})) {
      for (const ev of Object.values(bucket || {})) {
        const xml = String(ev.EXTERNAL_ID || ev.xml_id || '');
        const name = String(ev.NAME || ev.name || '');
        if (xml.includes('cs2_iem') || /cologne|iem/i.test(name)) {
          return Number(ev.ID || ev.id);
        }
      }
    }
  }
  return 76284;
}

async function main() {
  const token = await login();
  const eventId = await resolveEventId();
  console.log('eventId', eventId);

  const existing = await bitrixAction('prognos9ys:main.RatingSetController.listPublic', {
    sport: 'cs2',
    eventId,
  });
  const byTitle = new Map((existing.sets || []).map((s) => [String(s.title).toLowerCase(), s]));

  let created = 0;
  let skipped = 0;

  for (const team of TEAMS) {
    const key = team.title.toLowerCase();
    if (byTitle.has(key)) {
      skipped++;
      console.log(`SKIP ${team.title} #${byTitle.get(key).id}`);
      continue;
    }

    const res = await bitrixAction('prognos9ys:main.RatingSetController.create', {
      userToken: token,
      visibility: 'open',
      sport: 'cs2',
      title: team.title,
      userIds: team.userIds,
      eventIds: [eventId],
    });
    const set = res.set || res.create?.set || res;
    created++;
    console.log(`OK  ${team.title} #${set.id} (${set.membersCount} members)`);
    await new Promise((r) => setTimeout(r, 200));
  }

  console.log(`\nCreated: ${created}, skipped: ${skipped}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
