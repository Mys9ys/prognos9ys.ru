/**
 * Регистрация игроков и тренеров команд IEM Cologne Major 2026 (плей-офф).
 * Аккаунты @prognos9ys.ru автоматически попадают в группу «тестовые» (ID=6).
 *
 *   node local/tools/seed_cs2_iem_players_prod.mjs --event=12345
 *   node local/tools/seed_cs2_iem_players_prod.mjs --event=12345 --dry-run
 *   node local/tools/seed_cs2_iem_players_prod.mjs --event=12345 --only=cs2p_donk
 *
 * ID события: админка → IEM Cologne Major 2026 (XML_ID cs2_iem_cologne_2026).
 */

import { createRequire } from 'module';
import { mkdirSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const sharp = require('../../frontend/node_modules/sharp');

const __dir = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(__dir, 'output');
const BASE = process.env.PROGNOS_BASE || 'https://prognos9ys.ru';
const DEFAULT_AVA = '/upload/main/d8e/d8e464c093083bc55434c13989838971.jpeg';
const AVATAR_SIZE = 512;
const DRY_RUN = process.argv.includes('--dry-run');
const SKIP_PROG = process.argv.includes('--skip-prognosis');
const eventArg = process.argv.find((a) => a.startsWith('--event='));
const EVENT_ID = eventArg ? eventArg.split('=')[1].trim() : '';
const onlyArg = process.argv.find((a) => a.startsWith('--only='));
const ONLY = onlyArg ? onlyArg.split('=')[1].split(',').map((s) => s.trim().toLowerCase()) : null;
const matchArg = process.argv.find((a) => a.startsWith('--match='));
const MATCH_NUMBER = matchArg ? matchArg.split('=')[1].trim() : '';

/** 8 команд плей-офф IEM Cologne 2026 — 5 игроков + тренер */
const ROSTER = [
  {
    team: 'Team Spirit',
    tag: 'SPI',
    players: ['donk', 'sh1ro', 'chopper', 'zont1x', 'magixx'],
    coach: 'hally',
  },
  {
    team: 'FURIA',
    tag: 'FUR',
    players: ['FalleN', 'yuurih', 'KSCERATO', 'YEKINDAR', 'molodoy'],
    coach: 'sidde',
  },
  {
    team: 'Aurora',
    tag: 'AUR',
    players: ['MAJ3R', 'XANTARES', 'woxic', 'Wicadia', 'jottAAA'],
    coach: 'casN',
  },
  {
    team: 'Vitality',
    tag: 'VIT',
    players: ['apEX', 'ZywOo', 'flameZ', 'mezii', 'ropz'],
    coach: 'XTQZZZ',
  },
  {
    team: 'Falcons',
    tag: 'FLC',
    players: ['NiKo', 'm0NESY', 'TeSeS', 'kyxsan', 'kyousuke'],
    coach: 'zonic',
  },
  {
    team: 'BetBoom',
    tag: 'BB',
    players: ['Boombl4', 'Magnojez', 'zorte', 'd1Ledez', 'S1ren'],
    coach: 'hooch',
  },
  {
    team: '9z',
    tag: '9Z',
    players: ['max', 'Luken', 'urban0', 'levi', 'HUASOPEEK'],
    coach: 'taao',
  },
  {
    team: 'G2',
    tag: 'G2',
    players: ['huNter-', 'malbsMd', 'SunPayus', 'HeavyGod', 'MATYS'],
    coach: 'bLitz',
  },
];

function slugNick(nick) {
  return nick
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
    .slice(0, 24);
}

function flattenPeople() {
  const list = [];
  for (const row of ROSTER) {
    for (const nick of row.players) {
      const slug = slugNick(nick);
      list.push({
        team: row.team,
        tag: row.tag,
        nick,
        login: `cs2p_${slug}`,
        role: 'ИГ',
        wiki: `${nick}_(counter-strike_player)`,
      });
    }
    const coachSlug = slugNick(row.coach);
    list.push({
      team: row.team,
      tag: row.tag,
      nick: row.coach,
      login: `cs2c_${coachSlug}`,
      role: 'ТР',
      wiki: `${row.coach}_(counter-strike)`,
    });
  }
  return list;
}

const PEOPLE = flattenPeople();

function genPass() {
  const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
  let s = '';
  for (let i = 0; i < 10; i++) s += chars[Math.floor(Math.random() * chars.length)];
  return s;
}

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

async function formPost(path, fields) {
  const body = new URLSearchParams();
  Object.entries(fields).forEach(([k, v]) => body.append(k, String(v ?? '')));
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  });
  return res.json();
}

async function prepareSquareFaceAvatar(inputBuf) {
  return sharp(inputBuf)
    .rotate()
    .resize(AVATAR_SIZE, AVATAR_SIZE, { fit: 'cover', position: 'attention' })
    .jpeg({ quality: 86, mozjpeg: true })
    .toBuffer();
}

async function fetchWikiAvatar(wikiTitle, fallbackLabel) {
  const url = `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(wikiTitle)}`;
  try {
    const res = await fetch(url, {
      headers: { 'User-Agent': 'Prognos9ysSeedBot/1.0 (contact@prognos9ys.ru)' },
    });
    if (res.ok) {
      const data = await res.json();
      const src = data?.thumbnail?.source;
      if (src) {
        const imgRes = await fetch(src, { headers: { 'User-Agent': 'Prognos9ysSeedBot/1.0' } });
        if (imgRes.ok) {
          const buf = Buffer.from(await imgRes.arrayBuffer());
          if (buf.length > 500) return prepareSquareFaceAvatar(buf);
        }
      }
    }
  } catch {
    // fallback
  }
  const fb = `https://ui-avatars.com/api/?name=${encodeURIComponent(fallbackLabel)}&size=${AVATAR_SIZE}&background=f59e0b&color=fff&bold=true`;
  const res = await fetch(fb);
  if (!res.ok) return null;
  return prepareSquareFaceAvatar(Buffer.from(await res.arrayBuffer()));
}

async function registerPerson(person, pass) {
  const mail = `${person.login.toLowerCase()}@prognos9ys.ru`;
  const displayName = `${person.nick} (${person.tag}) [${person.role}]`;
  return formPost('/mob_app/ajax/register/', {
    type: 'reg',
    nick: displayName,
    mail,
    pass,
    pass2: pass,
    file: DEFAULT_AVA,
  });
}

async function loginPerson(mail, pass) {
  const res = await formPost('/mob_app/ajax/auth/', { type: 'newLogin', mail, pass });
  if (res.status !== 'ok' || !res.info?.UF_TOKEN) throw new Error(res.mes || 'login failed');
  return res.info.UF_TOKEN;
}

async function uploadAvatar(token, imageBuf, filename) {
  const form = new FormData();
  form.append('type', 'ava');
  form.append('token', token);
  form.append('file', new Blob([imageBuf], { type: 'image/jpeg' }), filename);
  const res = await fetch(`${BASE}/mob_app/ajax/auth/`, { method: 'POST', body: form });
  return res.json();
}

async function pickActiveMatch() {
  if (!EVENT_ID) {
    throw new Error('Укажите --event=ID (элемент events IEM Cologne Major 2026)');
  }

  const data = await bitrixAction('prognos9ys:main.Cs2Controller.getEventMatches', {
    events: String(EVENT_ID),
  });

  if ((data?.status || '') !== 'ok') {
    throw new Error('Не удалось загрузить матчи события');
  }

  const wanted = MATCH_NUMBER ? [MATCH_NUMBER] : ['5', '6'];
  for (const section of Object.values(data.info || {})) {
    const items = section?.items || {};
    for (const byDate of Object.values(items)) {
      for (const [num, match] of Object.entries(byDate || {})) {
        if (match?.active === 'Y' && wanted.includes(String(num))) {
          return { number: String(num), id: match.id, bo: match.bo_format || 'bo3' };
        }
      }
    }
  }

  if (MATCH_NUMBER) {
    const one = await bitrixAction('prognos9ys:main.Cs2Controller.getMatch', {
      eventId: String(EVENT_ID),
      number: String(MATCH_NUMBER),
    });
    const match = one?.result;
    if (match?.id && match.active === 'Y') {
      return { number: String(MATCH_NUMBER), id: match.id, bo: match.bo_format || 'bo3' };
    }
  }

  throw new Error('Нет активного матча для прогноза (полуфиналы #5/#6)');
}

function buildCs2Prognosis(matchMeta, index) {
  const bo = String(matchMeta.bo || 'bo3').toLowerCase();
  const maxWin = bo === 'bo1' ? 1 : bo === 'bo5' ? 3 : 2;
  const home = 1 + (index % maxWin);
  const guest = index % 2;
  const diff = home - guest;
  let result = '';
  if (diff > 0) result = 'п1';
  if (diff < 0) result = 'п2';

  const mapCount = bo === 'bo1' ? 1 : bo === 'bo5' ? 3 : 2;
  const maps = [];
  for (let i = 0; i < mapCount; i++) {
    maps.push({
      slot: i + 1,
      map_code: '',
      rounds_home: 13 + (i % 2),
      rounds_guest: 8 + (i % 3),
    });
  }

  return {
    30: Number(matchMeta.number),
    17: Number(matchMeta.id),
    15: home,
    16: guest,
    18: result,
    19: diff,
    28: home + guest,
    32: 45 + (index % 11),
    20: 50,
    21: 1 + (index % 3),
    22: index % 2,
    52: Number(EVENT_ID),
    29: JSON.stringify(maps),
    map_scores_json: JSON.stringify(maps),
  };
}

async function sendCs2Prognosis(token, fields) {
  const mapJson = fields.map_scores_json || fields[29];
  const payload = { ...fields };
  delete payload.map_scores_json;
  const data = await bitrixAction('prognos9ys:main.Cs2Controller.sendPrognosis', {
    userToken: token,
    fields: payload,
    map_scores_json: mapJson,
    withBet: false,
  });
  return data?.sendPrognosis || data;
}

async function main() {
  mkdirSync(OUT_DIR, { recursive: true });
  console.log(DRY_RUN ? '[DRY RUN]' : '[LIVE]', BASE);
  console.log(`CS2 IEM: ${PEOPLE.length} аккаунтов (игроки + тренеры)\n`);

  let matchMeta = null;
  if (!SKIP_PROG && !DRY_RUN) {
    matchMeta = await pickActiveMatch();
    console.log(`Матч #${matchMeta.number} id=${matchMeta.id} (${matchMeta.bo})\n`);
  }

  const credentials = [];
  let ok = 0;
  let fail = 0;

  for (let i = 0; i < PEOPLE.length; i++) {
    const person = PEOPLE[i];
    if (ONLY && !ONLY.includes(person.login.toLowerCase())) continue;

    const mail = `${person.login.toLowerCase()}@prognos9ys.ru`;
    const pass = genPass();

    if (DRY_RUN) {
      console.log(`${person.team.padEnd(16)} ${person.role} ${person.nick.padEnd(12)} <${mail}>`);
      continue;
    }

    try {
      const reg = await registerPerson(person, pass);
      if (reg.status !== 'ok') {
        console.log(`REG skip ${person.login}: ${reg.mes || 'exists?'}`);
      }

      const token = await loginPerson(mail, pass);

      const avatarBuf = await fetchWikiAvatar(person.wiki, person.nick);
      let avaOk = false;
      if (avatarBuf) {
        const avaRes = await uploadAvatar(token, avatarBuf, `${person.login}.jpg`);
        avaOk = avaRes.status === 'ok';
      }

      let progOk = true;
      let score = '-';
      if (matchMeta) {
        const fields = buildCs2Prognosis(matchMeta, i);
        const send = await sendCs2Prognosis(token, fields);
        progOk = (send?.status || '') === 'ok';
        score = `${fields[15]}:${fields[16]}`;
      }

      if (progOk) {
        ok++;
        credentials.push({
          team: person.team,
          role: person.role,
          nick: person.nick,
          mail,
          pass,
          ava: avaOk ? 'yes' : 'fallback',
          score,
        });
        console.log(
          `OK  ${person.team.padEnd(16)} ${person.role} ${person.nick.padEnd(12)} grp:6 ava:${avaOk ? '✓' : '·'} ${score}`,
        );
      } else {
        fail++;
        console.log(`FAIL ${person.login} prognosis`);
      }

      await new Promise((r) => setTimeout(r, 350));
    } catch (e) {
      fail++;
      console.log(`ERR ${person.login}: ${e.message}`);
    }
  }

  console.log(`\nDone: ${ok} ok, ${fail} failed`);
  console.log('Группа 6: назначается автоматически при регистрации (@prognos9ys.ru)');

  if (credentials.length) {
    const outFile = join(OUT_DIR, 'cs2_iem_credentials.tsv');
    const header = 'team\trole\tnick\tmail\tpass\tava\tscore\n';
    const lines = credentials.map((r) =>
      [r.team, r.role, r.nick, r.mail, r.pass, r.ava, r.score].join('\t'),
    );
    writeFileSync(outFile, header + lines.join('\n') + '\n', { flag: 'a' });
    console.log(`\nCredentials: ${outFile}`);
    console.log('Сброс паролей: php local/tools/reset_cs2_seed_passwords.php --confirm  →  {login}26');
    console.log('Бот CS2: AgentCs2BotSetPrognosis() в агентах Bitrix (как футбол)');
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
