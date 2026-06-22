export function mapSlotsForFormat(boFormat) {
  const value = String(boFormat || 'bo3').toLowerCase();
  if (value === 'bo1') return 1;
  if (value === 'bo5') return 5;
  return 3;
}

export function maxMapsWin(boFormat) {
  const value = String(boFormat || 'bo3').toLowerCase();
  if (value === 'bo1') return 1;
  if (value === 'bo5') return 3;
  return 2;
}

export function boFormatLabel(boFormat) {
  const value = String(boFormat || 'bo3').toLowerCase();
  if (value === 'bo1') return 'Bo1';
  if (value === 'bo5') return 'Bo5';
  return 'Bo3';
}

export function emptyMapScores(count) {
  return Array.from({ length: count }, (_, index) => ({
    slot: index + 1,
    map_id: 0,
    map_code: '',
    rounds_home: 0,
    rounds_guest: 0,
    pick_by: '',
  }));
}

export function normalizeMapScores(raw, slotCount) {
  const base = emptyMapScores(slotCount);
  if (!raw) {
    return base;
  }

  const list = Array.isArray(raw) ? raw : [];
  list.forEach((item, index) => {
    if (!base[index]) return;
    base[index] = {
      slot: index + 1,
      map_id: Number(item.map_id ?? 0),
      map_code: item.map_code || item.map || '',
      rounds_home: Number(item.rounds_home ?? item.home ?? 0),
      rounds_guest: Number(item.rounds_guest ?? item.guest ?? 0),
      pick_by: item.pick_by || '',
    };
  });

  return base;
}
