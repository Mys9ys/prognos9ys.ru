/** @typedef {'score'|'outcome'|'sum'|'diff'|'possession'|'corners'|'yellow'|'red'|'penalty'|'extra_time'|'shootout'|'rating_all'|'rating_prodigy'|'total_all'} FootballMetricKey */

/** @type {Record<FootballMetricKey, string>} */
export const FOOTBALL_METRIC_ICONS = {
  score: require('@/assets/icons/metrics/metric_score.png'),
  outcome: require('@/assets/icons/metrics/metric_outcome.png'),
  sum: require('@/assets/icons/metrics/metric_sum.png'),
  diff: require('@/assets/icons/metrics/metric_diff.png'),
  possession: require('@/assets/icons/metrics/metric_possession.png'),
  corners: require('@/assets/icons/metrics/metric_corners.png'),
  yellow: require('@/assets/icons/metrics/metric_yellow.png'),
  red: require('@/assets/icons/metrics/metric_red.png'),
  penalty: require('@/assets/icons/metrics/metric_penalty.png'),
  extra_time: require('@/assets/icons/metrics/metric_extra_time.png'),
  shootout: require('@/assets/icons/metrics/metric_shootout.png'),
  rating_all: require('@/assets/icons/metrics/metric_rating_all.png'),
  rating_prodigy: require('@/assets/icons/metrics/metric_rating_prodigy.png'),
  total_all: require('@/assets/icons/metrics/metric_total_all.png'),
};

const PROGNOSIS_FIELD = {
  1: 'score',
  18: 'outcome',
  19: 'sum',
  28: 'diff',
  32: 'possession',
  21: 'yellow',
  22: 'red',
  20: 'corners',
  23: 'penalty',
  45: 'extra_time',
  46: 'shootout',
};

const RESULT_TABLE = {
  1: 'score',
  2: 'outcome',
  3: 'diff',
  4: 'sum',
  5: 'possession',
  6: 'yellow',
  7: 'red',
  8: 'corners',
  9: 'penalty',
  10: 'extra_time',
  11: 'shootout',
  12: 'total_all',
};

const ADMIN_FIELD = {
  1: 'score',
  9: 'outcome',
  25: 'diff',
  26: 'sum',
  10: 'possession',
  12: 'yellow',
  13: 'red',
  11: 'corners',
  14: 'penalty',
  47: 'extra_time',
  48: 'shootout',
};

const RATING_TAB = {
  1: 'rating_all',
  2: 'score',
  18: 'outcome',
  28: 'diff',
  19: 'sum',
  32: 'possession',
  21: 'yellow',
  22: 'red',
  20: 'corners',
  23: 'penalty',
  45: 'extra_time',
  46: 'shootout',
  100: 'rating_prodigy',
};

const CONTEXT_MAP = {
  prognosis: PROGNOSIS_FIELD,
  resultTable: RESULT_TABLE,
  admin: ADMIN_FIELD,
  rating: RATING_TAB,
};

/**
 * @param {'prognosis'|'resultTable'|'admin'|'rating'} context
 * @param {number|string} fieldId
 * @returns {FootballMetricKey|null}
 */
export function getFootballMetricKey(context, fieldId) {
  return CONTEXT_MAP[context]?.[Number(fieldId)] || null;
}

/**
 * @param {'prognosis'|'resultTable'|'admin'|'rating'} context
 * @param {number|string} fieldId
 */
export function getFootballMetricIconSrc(context, fieldId) {
  const key = getFootballMetricKey(context, fieldId);
  return key ? FOOTBALL_METRIC_ICONS[key] : null;
}

/** @param {FootballMetricKey} key */
export function getFootballMetricIconByKey(key) {
  return FOOTBALL_METRIC_ICONS[key] || null;
}
