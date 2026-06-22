/**
 * Парсит матчи IEM Cologne 2026 из Liquipedia (MediaWiki API) → JSON для import_cs2_iem_matches.php
 *
 *   node local/tools/parse_cs2_iem_liquipedia.mjs
 *   node local/tools/parse_cs2_iem_liquipedia.mjs --out=local/tools/output/cs2_iem_matches.json
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const PAGES = [
    { stage: 'Stage 1', page: 'Intel_Extreme_Masters/2026/Cologne/Stage_1', roundOffset: 0 },
    { stage: 'Stage 2', page: 'Intel_Extreme_Masters/2026/Cologne/Stage_2', roundOffset: 5 },
    { stage: 'Stage 3', page: 'Intel_Extreme_Masters/2026/Cologne/Stage_3', roundOffset: 5 },
    { stage: 'Четвертьфинал', page: 'Intel_Extreme_Masters/2026/Cologne/Playoffs', roundOffset: 1, playoff: true },
];

/** Liquipedia TeamOpponent code → наш code в cs2teams */
const TEAM_ALIASES = {
    betboom: 'betboom',
    b8: 'b8',
    gl: 'gamerlegion',
    gamerlegion: 'gamerlegion',
    m80: 'm80',
    mibr: 'mibr',
    tyloo: 'tyloo',
    big: 'big',
    fq: 'flyquest',
    flyquest: 'flyquest',
    tl: 'liquid',
    liquid: 'liquid',
    nrg: 'nrg',
    lvg: 'lynnvision',
    lynnvision: 'lynnvision',
    thunderdownunder: 'thunder',
    thunder: 'thunder',
    sharks: 'sharks',
    heroic: 'heroic',
    gaimin: 'gaimin',
    sinners: 'sinners',
    spirit: 'spirit',
    '9z': '9z',
    fut: 'fut',
    g2: 'g2',
    monte: 'monte',
    legacy: 'legacy',
    pain: 'pain',
    astralis: 'astralis',
    furia: 'furia',
    mouz: 'mouz',
    natusvincere: 'navi',
    navi: 'navi',
    aurora: 'aurora',
    vitality: 'vitality',
    falcons: 'falcons',
    mongolz: 'mongolz',
    themongolz: 'mongolz',
    parivision: 'parivision',
    bb: 'betboom',
    vit: 'vitality',
    flc: 'falcons',
    ts: 'spirit',
};

const MAP_CODES = {
    'dust ii': 'dust2',
    dust2: 'dust2',
    mirage: 'mirage',
    inferno: 'inferno',
    nuke: 'nuke',
    ancient: 'ancient',
    anubis: 'anubis',
    overpass: 'overpass',
    train: 'train',
};

function normalizeTeam(raw) {
    const key = String(raw || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    return TEAM_ALIASES[key] || key;
}

function normalizeMap(raw) {
    const key = String(raw || '').toLowerCase().trim();
    return MAP_CODES[key] || key.replace(/\s+/g, '');
}

function parseRounds(mapBlock) {
    const t1t = Number(mapBlock.match(/t1t=(\d+)/)?.[1] ?? 0);
    const t1ct = Number(mapBlock.match(/t1ct=(\d+)/)?.[1] ?? 0);
    const t2t = Number(mapBlock.match(/t2t=(\d+)/)?.[1] ?? 0);
    const t2ct = Number(mapBlock.match(/t2ct=(\d+)/)?.[1] ?? 0);
    return { home: t1t + t1ct, guest: t2t + t2ct };
}

function parseMatchBlock(block, stage, roundHint) {
    const homeRaw = block.match(/opponent1=\{\{TeamOpponent\|([^}|]+)/)?.[1];
    const guestRaw = block.match(/opponent2=\{\{TeamOpponent\|([^}\n|]+)/)?.[1];
    if (!homeRaw || !guestRaw) return null;

    const dateMatch = block.match(/date=June (\d+), 2026[^|]*?(\d{1,2}):(\d{2})/);
    if (!dateMatch) return null;

    const day = dateMatch[1].padStart(2, '0');
    const hour = dateMatch[2].padStart(2, '0');
    const minute = dateMatch[3];
    const date = `${day}.06.2026 ${hour}:${minute}:00`;

    const mapBlocks = [...block.matchAll(/\|map(\d+)=\{\{Map\|map=([^|\n]+)[\s\S]*?\|finished=(true|skip)/g)];
    const playedMaps = mapBlocks.filter((m) => m[3] === 'true');

    let mapsHome = 0;
    let mapsGuest = 0;
    const mapScores = [];

    playedMaps.forEach((m, index) => {
        const mapNum = Number(m[1]);
        const mapStart = block.indexOf(m[0]);
        const nextPattern = new RegExp(`\\|map${mapNum + 1}=`);
        const nextPos = block.search(nextPattern);
        const slice = nextPos > 0 ? block.slice(mapStart, nextPos) : block.slice(mapStart);
        const rounds = parseRounds(slice);
        if (rounds.home > rounds.guest) mapsHome++;
        else if (rounds.guest > rounds.home) mapsGuest++;

        mapScores.push({
            slot: index + 1,
            map_code: normalizeMap(m[2]),
            rounds_home: rounds.home,
            rounds_guest: rounds.guest,
        });
    });

    const boFormat = playedMaps.length <= 1 ? 'bo1' : (playedMaps.length >= 5 ? 'bo5' : 'bo3');
    const finished = /finished=true/.test(block) && playedMaps.length > 0;

    let result = '';
    if (mapsHome > mapsGuest) result = 'п1';
    else if (mapsGuest > mapsHome) result = 'п2';

    const roundMatch = block.match(/matchsection=Round (\d+)/);
    const round = roundMatch ? Number(roundMatch[1]) : roundHint;

    return {
        stage,
        round,
        home: normalizeTeam(homeRaw),
        guest: normalizeTeam(guestRaw),
        date,
        bo_format: boFormat,
        active: finished && mapsHome + mapsGuest > 0 ? 'N' : 'Y',
        result: finished && result ? {
            maps_home: mapsHome,
            maps_guest: mapsGuest,
            result,
            diff: mapsHome - mapsGuest,
            sum: mapsHome + mapsGuest,
            opening_pct: 50,
            pistol_pct: 50,
            clutches_home: 0,
            clutches_guest: 0,
            map_scores: mapScores,
        } : null,
    };
}

async function fetchWikitext(page, useCache = false) {
    const cacheDir = path.join(__dirname, 'output', 'liquipedia_cache');
    const cacheFile = path.join(cacheDir, page.replace(/[\\/]/g, '_') + '.json');

    if (useCache && fs.existsSync(cacheFile)) {
        const cached = JSON.parse(fs.readFileSync(cacheFile, 'utf8'));
        return cached.wikitext || '';
    }

    const url = `https://liquipedia.net/counterstrike/api.php?action=parse&page=${encodeURIComponent(page)}&prop=wikitext&format=json`;
    const res = await fetch(url, { headers: { 'User-Agent': 'prognos9ys-cs2-import/1.0' } });
    if (!res.ok) throw new Error(`HTTP ${res.status} for ${page}`);
    const json = await res.json();
    const wikitext = json?.parse?.wikitext?.['*'] || '';

    fs.mkdirSync(cacheDir, { recursive: true });
    fs.writeFileSync(cacheFile, JSON.stringify({ page, wikitext }, null, 0), 'utf8');

    return wikitext;
}

function extractMatches(wikitext, stage, playoff = false) {
    const matches = [];
    const regex = /\|?\{\{Match\n([\s\S]*?)\|hltv=\d+/g;
    let match;

    while ((match = regex.exec(wikitext)) !== null) {
        const block = '|{{Match\n' + match[1];
        const roundHint = Number(block.match(/matchsection=Round (\d+)/)?.[1] || 0);
        let stageName = stage;
        if (playoff) {
            const pos = match.index;
            const before = wikitext.slice(Math.max(0, pos - 400), pos);
            if (/Grand Final|Grand final|Bo5/i.test(block)) {
                stageName = 'Финал';
            } else if (/Semifinals|Semifinal/i.test(before)) {
                stageName = 'Полуфинал';
            } else {
                stageName = 'Четвертьфинал';
            }
        }
        const parsed = parseMatchBlock(block, stageName, roundHint);
        if (parsed) {
            matches.push(parsed);
        }
    }

    return matches;
}

async function main() {
    const outArg = process.argv.find((a) => a.startsWith('--out='));
    const useCache = process.argv.includes('--cache') || process.argv.includes('--cache-only');
    const outPath = outArg
        ? path.resolve(outArg.split('=')[1])
        : path.join(__dirname, 'output', 'cs2_iem_matches.json');

    const all = [];
    let number = 1;

    for (const cfg of PAGES) {
        console.log(`Fetching ${cfg.page}...`);
        const wikitext = await fetchWikitext(cfg.page, useCache);
        const stageMatches = extractMatches(wikitext, cfg.stage, cfg.playoff);
        stageMatches.forEach((m) => {
            all.push({ ...m, number: number++ });
        });
        console.log(`  ${stageMatches.length} matches`);
    }

    const teams = new Set();
    all.forEach((m) => {
        teams.add(m.home);
        teams.add(m.guest);
    });

    const payload = {
        event_xml_id: 'cs2_iem_cologne_2026',
        generated_at: new Date().toISOString(),
        teams: [...teams].sort(),
        matches: all,
    };

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(payload, null, 2), 'utf8');
    console.log(`\nWrote ${all.length} matches, ${teams.size} teams → ${outPath}`);
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
