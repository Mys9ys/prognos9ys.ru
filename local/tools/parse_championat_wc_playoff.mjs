/**
 * Парсит playoff-таблицы из сохранённой страницы Championat (markdown).
 *
 * Usage:
 *   node local/tools/parse_championat_wc_playoff.mjs [input.md] [output.json]
 */
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const inputPath = process.argv[2]
  || join(__dirname, '../../.cursor/projects/d-OSPanel-home-prognos9ys/uploads/table-0.md');
const outputPath = process.argv[3]
  || join(__dirname, 'output/wc2026_playoff_bracket.json');

const ROUND_SECTIONS = [
  { key: 'r32', title: '1/16 финала', label: '1/16', round: 1 },
  { key: 'r16', title: '1/8 финала', label: '1/8', round: 2 },
  { key: 'qf', title: '1/4 финала', label: '1/4', round: 3 },
  { key: 'sf', title: '1/2 финала', label: '1/2', round: 4 },
  { key: 'third', title: 'За 3-е место', label: '3-е место', round: 5 },
  { key: 'final', title: 'Финал', label: 'Финал', round: 6 },
];

function stripMarkdownLink(text) {
  return String(text || '')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/!\[[^\]]*]\([^)]+\)/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizeTeamName(name) {
  if (!name) {
    return name;
  }
  return String(name)
    .replace(/[`´ʼ′’]/g, "'")
    .replace(/\s+/g, ' ')
    .trim();
}

function parseTeamCell(cell) {
  const linkNames = [...cell.matchAll(/\[([^\]]+)\]\([^)]+\)/g)]
    .map((m) => m[1].trim())
    .filter((name) => name && !/^\d{2}\.\d{2}/.test(name));

  const slotTokens = stripMarkdownLink(
    cell.replace(/\[([^\]]+)\]\([^)]+\)/g, ' ').replace(/!\[[^\]]*]\([^)]+\)/g, ' ')
  )
    .split(/\s+/)
    .map((t) => t.trim())
    .filter(Boolean)
    .filter((token) => isSlotLabel(token));

  if (linkNames.length >= 2) {
    return { home: normalizeTeamName(linkNames[0]), guest: normalizeTeamName(linkNames[1]) };
  }

  if (linkNames.length === 1 && slotTokens.length >= 1) {
    return { home: normalizeTeamName(linkNames[0]), guest: slotTokens[0] };
  }

  if (slotTokens.length >= 2) {
    return { home: slotTokens[0], guest: slotTokens[1] };
  }

  if (linkNames.length === 1) {
    return { home: normalizeTeamName(linkNames[0]), guest: null };
  }

  if (slotTokens.length === 1) {
    return { home: slotTokens[0], guest: null };
  }

  return { home: null, guest: null };
}

function isSlotLabel(name) {
  if (!name) {
    return false;
  }
  return /^[123][A-L]$/.test(name)
    || /^3[A-Z]{2,}$/.test(name)
    || /^[AB]\d{2}$/.test(name)
    || /^QF[1-4]$/.test(name)
    || /^SF[12]$/.test(name)
    || /^LSF[12]$/.test(name)
    || /^F[13]$/.test(name);
}

function parseDateCell(cell) {
  const text = stripMarkdownLink(cell);
  const m = text.match(/(\d{2})\.(\d{2})(?:\.(\d{4}))?/);
  if (!m) {
    return null;
  }
  const day = m[1];
  const month = m[2];
  const year = m[3] || '2026';
  return `${day}.${month}.${year}`;
}

function parseSection(lines, startIdx, endIdx) {
  const matches = [];
  for (let i = startIdx; i < endIdx; i += 1) {
    const line = lines[i].trim();
    if (!line.startsWith('|') || line.includes('Метка') || line.includes('---')) {
      continue;
    }

    const cols = line
      .split('|')
      .map((c) => c.trim())
      .filter((_, idx, arr) => idx > 0 && idx < arr.length);

    if (cols.length < 4) {
      continue;
    }

    const bracketCode = cols[1];
    if (!bracketCode || bracketCode === 'Метка') {
      continue;
    }

    const teams = parseTeamCell(cols[2]);
    const date = parseDateCell(cols[3]);

    matches.push({
      bracket_code: bracketCode,
      home: teams.home,
      guest: teams.guest,
      date,
      home_is_slot: teams.home ? !isKnownTeamName(teams.home) : false,
      guest_is_slot: teams.guest ? !isKnownTeamName(teams.guest) : false,
    });
  }
  return matches;
}

function isKnownTeamName(name) {
  return Boolean(name) && !isSlotLabel(name);
}

function findSectionBounds(lines) {
  const bounds = {};
  ROUND_SECTIONS.forEach((section, index) => {
    const starts = [];
    lines.forEach((line, idx) => {
      if (line.trim() === section.title) {
        starts.push(idx);
      }
    });
    const start = starts.length ? starts[starts.length - 1] : -1;
    if (start < 0) {
      return;
    }
    let end = lines.length;
    for (let j = index + 1; j < ROUND_SECTIONS.length; j += 1) {
      const nextTitle = ROUND_SECTIONS[j].title;
      const nextStarts = [];
      lines.forEach((line, idx) => {
        if (line.trim() === nextTitle) {
          nextStarts.push(idx);
        }
      });
      const next = nextStarts.length ? nextStarts[nextStarts.length - 1] : -1;
      if (next > start) {
        end = next;
        break;
      }
    }
    bounds[section.key] = { start: start + 1, end, meta: section };
  });
  return bounds;
}

const source = readFileSync(inputPath, 'utf8');
const lines = source.split(/\r?\n/);
const bounds = findSectionBounds(lines);

const payload = {
  source: 'championat.com/football/_worldcup/tournament/6858/table/#playoff',
  parsed_at: new Date().toISOString(),
  rounds: [],
};

let step = 0;
ROUND_SECTIONS.forEach((section) => {
  const block = bounds[section.key];
  if (!block) {
    return;
  }
  const matches = parseSection(lines, block.start, block.end).map((match, index) => {
    step += 1;
    return {
      ...match,
      round: section.round,
      step: index + 1,
      stage_label: section.label,
      sort_step: step,
    };
  });

  payload.rounds.push({
    key: section.key,
    label: section.label,
    round: section.round,
    matches,
  });
});

payload.totals = {
  matches: payload.rounds.reduce((sum, r) => sum + r.matches.length, 0),
  known_team_sides: payload.rounds.reduce((sum, r) => (
    sum + r.matches.reduce((s, m) => s + (m.home && !m.home_is_slot ? 1 : 0) + (m.guest && !m.guest_is_slot ? 1 : 0), 0)
  ), 0),
  slot_sides: payload.rounds.reduce((sum, r) => (
    sum + r.matches.reduce((s, m) => s + (m.home_is_slot ? 1 : 0) + (m.guest_is_slot ? 1 : 0), 0)
  ), 0),
};

writeFileSync(outputPath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
console.log(`Parsed ${payload.totals.matches} matches → ${outputPath}`);
console.log(`Known teams: ${payload.totals.known_team_sides}, slot labels: ${payload.totals.slot_sides}`);
