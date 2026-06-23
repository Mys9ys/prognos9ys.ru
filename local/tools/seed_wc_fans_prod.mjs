/**
 * Регистрация болельщиков (М/Ж) и правителей 48 сборных ЧМ-2026 на БОЮ prognos9ys.ru.
 * Запускается с локалки — все HTTP-запросы идут на https://prognos9ys.ru (как seed_wc_players_prod.mjs).
 *
 *   node local/tools/seed_wc_fans_prod.mjs --dry-run
 *   node local/tools/seed_wc_fans_prod.mjs
 *   node local/tools/seed_wc_fans_prod.mjs --only=fanmarg,rulerarg
 *
 * На сервере (альтернатива): php7.4 local/tools/seed_wc_fans_prod.php
 * Пароли: php local/tools/reset_wc_fan_passwords.php --confirm  →  {login}26
 */

import { mkdirSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { flattenFansRoster } from './wc_fans_roster.mjs';

const __dir = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(__dir, 'output');
const BASE = 'https://prognos9ys.ru';
const EVENT_ID = 63849;
const MATCH_NUMBER = 22;
const DEFAULT_AVA = '/upload/main/d8e/d8e464c093083bc55434c13989838971.jpeg';

const DRY_RUN = process.argv.includes('--dry-run');
const SKIP_PROG = process.argv.includes('--skip-prognosis');
const onlyArg = process.argv.find((a) => a.startsWith('--only='));
const ONLY = onlyArg ? onlyArg.split('=')[1].split(',').map((s) => s.trim().toLowerCase()) : null;

const PEOPLE = flattenFansRoster();

function genPass() {
  const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
  let s = '';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
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

async function registerPerson(person, pass) {
  const mail = person.mail;
  return formPost('/mob_app/ajax/register/', {
    type: 'reg',
    nick: `${person.name} (${person.role})`,
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

async function loadMatch() {
  const data = await bitrixAction('prognos9ys:main.FootballController.getMatch', {
    eventId: String(EVENT_ID),
    number: String(MATCH_NUMBER),
  });
  const match = data?.result;
  if (!match?.id) throw new Error('Матч не найден');
  return match;
}

function buildPrognosis(match, index) {
  const hg = 1 + (index % 3);
  const ag = (index + 1) % 4;
  const diff = hg - ag;
  let result = 'н';
  if (diff > 0) result = 'п1';
  if (diff < 0) result = 'п2';
  return {
    30: MATCH_NUMBER,
    17: Number(match.id),
    15: hg,
    16: ag,
    18: result,
    19: diff,
    28: hg + ag,
    32: 40 + (index % 15),
    21: 1 + (index % 3),
    22: index % 2,
    20: 4 + (index % 4),
    23: 0,
    52: EVENT_ID,
    45: '',
    46: '',
    29: '',
  };
}

async function sendPrognosis(token, fields) {
  const data = await bitrixAction('prognos9ys:main.FootballController.sendPrognosis', {
    userToken: token,
    fields,
    withBet: false,
  });
  return data?.sendPrognosis || data;
}

async function main() {
  mkdirSync(OUT_DIR, { recursive: true });
  console.log(DRY_RUN ? '[DRY RUN]' : '[LIVE]', BASE);
  console.log(`Болельщики + правители: ${PEOPLE.length} аккаунтов (48×3)\n`);

  let match = null;
  if (!DRY_RUN && !SKIP_PROG) {
    match = await loadMatch();
    console.log(`Match id=${match.id}: ${match.home?.name} — ${match.guest?.name}\n`);
  }

  const credentials = [];
  let ok = 0;
  let fail = 0;

  for (let i = 0; i < PEOPLE.length; i++) {
    const person = PEOPLE[i];
    if (ONLY && !ONLY.includes(person.login.toLowerCase())) continue;

    const mail = person.mail;
    const pass = genPass();

    if (DRY_RUN) {
      console.log(`${person.team.padEnd(22)} ${person.role.padEnd(5)} ${person.name} <${mail}>`);
      continue;
    }

    try {
      const reg = await registerPerson(person, pass);
      if (reg.status !== 'ok') {
        console.log(`REG exist? ${person.login}: ${reg.mes || ''}`);
      }

      const token = await loginPerson(mail, pass);

      let progOk = true;
      let score = '-';
      if (match) {
        const fields = buildPrognosis(match, i);
        const send = await sendPrognosis(token, fields);
        progOk = (send?.status || '') === 'ok';
        score = `${fields[15]}:${fields[16]}`;
      }

      if (progOk) {
        ok++;
        credentials.push({
          team: person.team,
          role: person.role,
          name: person.name,
          mail,
          pass,
          score,
        });
        console.log(
          `OK  ${person.team.padEnd(22)} ${person.role.padEnd(5)} ${person.name.padEnd(16)} ${score}`,
        );
      } else {
        fail++;
        console.log(`FAIL ${person.login} prognosis`);
      }

      await new Promise((r) => setTimeout(r, 300));
    } catch (e) {
      fail++;
      console.log(`ERR ${person.login}: ${e.message}`);
    }
  }

  console.log(`\nDone: ${ok} ok, ${fail} failed`);

  if (credentials.length) {
    const outFile = join(OUT_DIR, 'wc_fans_credentials.tsv');
    const header = 'team\trole\tname\tmail\tpass\tscore\n';
    const lines = credentials.map((r) =>
      [r.team, r.role, r.name, r.mail, r.pass, r.score].join('\t'),
    );
    writeFileSync(outFile, header + lines.join('\n') + '\n', { flag: 'a' });
    console.log(`\nCredentials appended: ${outFile}`);
    console.log('Tip: php local/tools/reset_wc_fan_passwords.php --confirm for password {login}26');
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
