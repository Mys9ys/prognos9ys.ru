const { execFileSync } = require('child_process');
const path = require('path');

const script = path.join(__dirname, 'png-to-game-icon.js');
const pairs = [
  { src: 'mob_app/img/bank_ps.png', dst: 'frontend/src/assets/icons/game/bank.png' },
  { src: 'mob_app/img/ball_ps.png', dst: 'frontend/src/assets/icons/game/football.png' },
  { src: 'mob_app/img/chest_ps.png', dst: 'frontend/src/assets/icons/game/chest-wc2026.png' },
  { src: 'mob_app/img/chest_xp_ps.png', dst: 'frontend/src/assets/icons/game/chest_xp.png' },
  { src: 'mob_app/img/rublius_ps.png', dst: 'frontend/src/assets/icons/game/rublius.png' },
  { src: 'mob_app/img/trophy_ps.png', dst: 'frontend/src/assets/icons/game/achievement.png' },
  { src: 'mob_app/img/prognobak.5d78b844.png', dst: 'frontend/src/assets/icons/game/prognobak.png' },
  { src: 'mob_app/img/f1_race_ps.png', dst: 'frontend/src/assets/icons/game/f1_race.png' },
  { src: 'mob_app/img/exit_door_ps.png', dst: 'frontend/src/assets/icons/game/exit_door.png' },
  { src: 'mob_app/img/profile_info_ps.png', dst: 'frontend/src/assets/icons/game/profile_info.png' },
  { src: 'mob_app/img/settings_ps.png', dst: 'frontend/src/assets/icons/game/settings.png' },
  { src: 'mob_app/img/wealth_ps.png', dst: 'frontend/src/assets/icons/game/wealth.png' },
  { src: 'mob_app/img/poverty_ps.png', dst: 'frontend/src/assets/icons/game/poverty.png' },
  { src: 'mob_app/img/xp_ps.png', dst: 'frontend/src/assets/icons/game/xp.png' },
  { src: 'mob_app/img/prognosis_ps.png', dst: 'frontend/src/assets/icons/game/prognosis.png' },
];

const metricPairs = [
  { src: 'local/tools/assets/metrics/ps/metric_score.png', dst: 'frontend/src/assets/icons/metrics/metric_score.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_outcome.png', dst: 'frontend/src/assets/icons/metrics/metric_outcome.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_sum.png', dst: 'frontend/src/assets/icons/metrics/metric_sum.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_diff.png', dst: 'frontend/src/assets/icons/metrics/metric_diff.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_possession.png', dst: 'frontend/src/assets/icons/metrics/metric_possession.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_corners.png', dst: 'frontend/src/assets/icons/metrics/metric_corners.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_yellow.png', dst: 'frontend/src/assets/icons/metrics/metric_yellow.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_red.png', dst: 'frontend/src/assets/icons/metrics/metric_red.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_penalty.png', dst: 'frontend/src/assets/icons/metrics/metric_penalty.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_extra_time.png', dst: 'frontend/src/assets/icons/metrics/metric_extra_time.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_shootout.png', dst: 'frontend/src/assets/icons/metrics/metric_shootout.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_rating_all.png', dst: 'frontend/src/assets/icons/metrics/metric_rating_all.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_rating_prodigy.png', dst: 'frontend/src/assets/icons/metrics/metric_rating_prodigy.png', size: '128' },
  { src: 'local/tools/assets/metrics/ps/metric_total_all.png', dst: 'frontend/src/assets/icons/metrics/metric_total_all.png', size: '128' },
];

const achievementPairs = [
  { src: 'local/tools/assets/achievements/ps/welcome.png', dst: 'frontend/src/assets/icons/achievements/ach_welcome.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/wc26.png', dst: 'frontend/src/assets/icons/achievements/ach_chm2026.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/prodigy.png', dst: 'frontend/src/assets/icons/achievements/ach_prodigy.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/luck.png', dst: 'frontend/src/assets/icons/achievements/ach_luck.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/score.png', dst: 'frontend/src/assets/icons/achievements/ach_scoreboard.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/way_red.png', dst: 'frontend/src/assets/icons/achievements/ach_wow_red.png', size: '128' },
  { src: 'local/tools/assets/achievements/ps/way_penalty.png', dst: 'frontend/src/assets/icons/achievements/ach_wow_pen.png', size: '128' },
];

const pennantPairs = [
  { src: 'local/tools/assets/achievements/ps/pennant_site.png', dst: 'frontend/src/assets/icons/pennants/pennant_site.png', size: '256' },
  { src: 'local/tools/assets/achievements/ps/pennant_wc26.png', dst: 'frontend/src/assets/icons/pennants/pennant_chm2026.png', size: '256' },
];

const inventoryPairs = [
  { src: 'local/tools/assets/achievements/ps/premium_scroll_1d.png', dst: 'frontend/src/assets/icons/inventory/premium_scroll_1d.png', size: '256' },
  { src: 'local/tools/assets/achievements/ps/achiv_chess.png', dst: 'frontend/src/assets/icons/game/chest-achievement.png', size: '256' },
];

const allPairs = [...pairs, ...metricPairs, ...achievementPairs, ...pennantPairs, ...inventoryPairs];

const root = path.join(__dirname, '..', '..');
const size = '256';

for (const item of allPairs) {
  const src = path.resolve(root, item.src);
  const dst = path.resolve(root, item.dst);
  execFileSync(process.execPath, [script, src, dst, item.size || size], { stdio: 'inherit' });
}
