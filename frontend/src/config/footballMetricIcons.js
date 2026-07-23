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

/** @type {Record<string, string>} */
export const ACHIEVEMENT_EXTRA_ICONS = {
  welcome: require('@/assets/icons/achievements/ach_welcome.png'),
  luck: require('@/assets/icons/achievements/ach_luck.png'),
  chm2026: require('@/assets/icons/achievements/ach_chm2026.png'),
  prodigy: require('@/assets/icons/achievements/ach_prodigy.png'),
  scoreboard: require('@/assets/icons/achievements/ach_scoreboard.png'),
  wow_red: require('@/assets/icons/achievements/ach_wow_red.png'),
  wow_pen: require('@/assets/icons/achievements/ach_wow_pen.png'),
  tipster: require('@/assets/icons/achievements/ach_tipster.png'),
  chest_warehouse: require('@/assets/icons/achievements/ach_chest_warehouse.png'),
  chest_opener: require('@/assets/icons/achievements/ach_chest_opener.png'),
  scrooge: require('@/assets/icons/achievements/ach_scrooge.png'),
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

/** @param {string} key */
export function getFootballMetricIconByKey(key) {
  return FOOTBALL_METRIC_ICONS[key] || null;
}

/** @param {string} key */
export function getAchievementIconSrc(key) {
  if (!key) {
    return null;
  }
  return FOOTBALL_METRIC_ICONS[key] || ACHIEVEMENT_EXTRA_ICONS[key] || null;
}

/**
 * Справочник ачивок: ключ иконки → metric_*.png или achievements/*.png.
 *
 * @type {Record<string, { icon: string, iconFile: string, stat: string, levels: number, description: string }>}
 */
export const ACHIEVEMENT_CATALOG_REF = {
  welcome: { icon: 'welcome', iconFile: 'ach_welcome.png', stat: 'football_prognosis', levels: 1, description: '1 футбольный прогноз' },
  prognosis: { icon: 'total_all', iconFile: 'metric_total_all.png', stat: 'football_prognosis', levels: 5, description: '5 / 10 / 50 / 100 / 500 прогнозов' },
  chm2026: { icon: 'chm2026', iconFile: 'ach_chm2026.png', stat: 'chm_prognosis', levels: 3, description: '10 / 50 / 100 прогнозов на ЧМ-2026' },
  rpl2627: { icon: 'total_all', iconFile: 'metric_total_all.png', stat: 'rpl_prognosis', levels: 3, description: '10 / 50 / 100 прогнозов на РПЛ 2026/27' },
  great_prediction: { icon: 'rating_prodigy', iconFile: 'metric_rating_prodigy.png', stat: 'score_30_39', levels: 5, description: '3 / 7 / 20 / 50 / 100 прогнозов по 30–39 баллов' },
  prodigy: { icon: 'prodigy', iconFile: 'ach_prodigy.png', stat: 'score_40_plus', levels: 5, description: '1 / 3 / 5 / 10 / 25 прогнозов по 40+ баллов' },
  better_luck: { icon: 'luck', iconFile: 'ach_luck.png', stat: 'score_0', levels: 5, description: '3 / 7 / 20 / 50 / 100 прогнозов с 0 баллов' },
  metric_exact_score: { icon: 'scoreboard', iconFile: 'ach_scoreboard.png', stat: 'metric_exact_score', levels: 5, description: '5 / 10 / 50 / 100 / 500 угаданных счетов матча' },
  metric_outcome: { icon: 'outcome', iconFile: 'metric_outcome.png', stat: 'metric_outcome', levels: 5, description: '5 / 10 / 50 / 100 / 500 угаданных исходов матча' },
  metric_total_goals: { icon: 'sum', iconFile: 'metric_sum.png', stat: 'metric_total_goals', levels: 5, description: '5 / 10 / 50 / 100 / 500 угаданных сумм голов' },
  metric_goal_diff: { icon: 'diff', iconFile: 'metric_diff.png', stat: 'metric_goal_diff', levels: 5, description: '5 / 10 / 50 / 100 / 500 угаданных разниц голов' },
  metric_corners: { icon: 'corners', iconFile: 'metric_corners.png', stat: 'metric_corners', levels: 5, description: '20 / 50 / 100 / 250 / 500 баллов за угловые' },
  metric_yellow: { icon: 'yellow', iconFile: 'metric_yellow.png', stat: 'metric_yellow', levels: 5, description: '20 / 50 / 100 / 250 / 500 баллов за жёлтые' },
  metric_possession: { icon: 'possession', iconFile: 'metric_possession.png', stat: 'metric_possession', levels: 5, description: '20 / 50 / 100 / 250 / 500 баллов за % владения' },
  rare_red: { icon: 'red', iconFile: 'metric_red.png', stat: 'rare_red', levels: 5, description: '5 / 10 / 25 / 50 / 100 фактов «красная» (ДА)' },
  rare_penalty: { icon: 'penalty', iconFile: 'metric_penalty.png', stat: 'rare_penalty', levels: 5, description: '5 / 10 / 25 / 50 / 100 фактов «пенальти» (ДА)' },
  wow_red: { icon: 'wow_red', iconFile: 'ach_wow_red.png', stat: 'wow_red', levels: 5, description: '1 / 3 / 5 / 10 / 20 точных кол-в красных (>1)' },
  wow_pen: { icon: 'wow_pen', iconFile: 'ach_wow_pen.png', stat: 'wow_pen', levels: 5, description: '1 / 3 / 5 / 10 / 20 точных кол-в пенальти (>1)' },
  metric_extra_time: { icon: 'extra_time', iconFile: 'metric_extra_time.png', stat: 'metric_extra_time', levels: 5, description: '5 / 10 / 20 / 50 / 100 фактов доп. времени' },
  metric_shootout: { icon: 'shootout', iconFile: 'metric_shootout.png', stat: 'metric_shootout', levels: 5, description: '5 / 10 / 20 / 50 / 100 серий пенальти' },
  rich_bettor: { icon: 'tipster', iconFile: 'ach_tipster.png', stat: 'bet_winnings_prognobaks', levels: 5, description: 'Выигрыши со ставок, прогнобаксы' },
  chest_pioneer: { icon: 'chest_opener', iconFile: 'ach_chest_opener.png', stat: 'chests_opened', levels: 5, description: 'Открытые сундуки (любой тип)' },
  rublius_trader: { icon: 'scrooge', iconFile: 'ach_scrooge.png', stat: 'rublius_earned', levels: 5, description: 'Заработанные рублиусы' },
  chest_collector: { icon: 'chest_warehouse', iconFile: 'ach_chest_warehouse.png', stat: 'chests_earned', levels: 5, description: 'Полученные сундуки (без лавки)' },
};
