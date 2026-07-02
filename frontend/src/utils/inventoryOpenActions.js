/** Максимум за одно действие «Все» (сундуки, паки, банки). */
export const INVENTORY_OPEN_MAX_BATCH = 30;

/**
 * Универсальные кнопки открытия для слота инвентаря.
 *
 * 1 шт. → «Открыть»
 * 2–5 → «Открыть» / «Все»
 * 6–10 → «Открыть» / «5» / «Все»
 * 11+ → «Открыть» / «5» / «10» / «Все»
 *
 * @param {number} count
 * @param {string} primaryLabel
 * @returns {Array<{qty:number,label:string,kind:'primary'|'batch'|'all'}>}
 */
export function buildInventoryOpenActions(count, primaryLabel = 'Открыть') {
  const total = Math.max(0, Math.floor(Number(count) || 0));
  const allQty = Math.min(total, INVENTORY_OPEN_MAX_BATCH);

  if (total <= 1) {
    return [{ qty: 1, label: primaryLabel, kind: 'primary' }];
  }

  if (total <= 5) {
    return [
      { qty: 1, label: primaryLabel, kind: 'primary' },
      { qty: allQty, label: 'Все', kind: 'all' },
    ];
  }

  if (total <= 10) {
    return [
      { qty: 1, label: primaryLabel, kind: 'primary' },
      { qty: Math.min(5, allQty), label: '5', kind: 'batch' },
      { qty: allQty, label: 'Все', kind: 'all' },
    ];
  }

  return [
    { qty: 1, label: primaryLabel, kind: 'primary' },
    { qty: 5, label: '5', kind: 'batch' },
    { qty: Math.min(10, allQty), label: '10', kind: 'batch' },
    { qty: allQty, label: 'Все', kind: 'all' },
  ];
}

/**
 * @param {number} count
 * @param {number} qty
 */
export function isInventoryOpenAllAction(count, qty) {
  const total = Math.max(0, Math.floor(Number(count) || 0));
  const allQty = Math.min(total, INVENTORY_OPEN_MAX_BATCH);

  return Math.floor(Number(qty) || 0) >= allQty && total > 1;
}

/**
 * Раскладка кнопок по рядам: «Открыть» | «5»/«10» | «Все».
 *
 * @param {Array<{qty:number,label:string,kind:string}>} actions
 * @returns {Array<Array<{qty:number,label:string,kind:string}>>}
 */
export function groupInventoryOpenActions(actions) {
  if (!Array.isArray(actions) || !actions.length) {
    return [];
  }

  const primary = actions.filter((action) => action.kind === 'primary');
  const batch = actions.filter((action) => action.kind === 'batch');
  const all = actions.filter((action) => action.kind === 'all');
  const rows = [];

  if (primary.length) {
    rows.push(primary);
  }
  if (batch.length) {
    rows.push(batch);
  }
  if (all.length) {
    rows.push(all);
  }

  return rows.length ? rows : [actions];
}
