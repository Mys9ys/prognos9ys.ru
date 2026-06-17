const { execFileSync } = require('child_process');
const path = require('path');

const script = path.join(__dirname, 'png-to-game-icon.js');
const pairs = [
  { src: 'mob_app/img/bank_ps.png', dst: 'frontend/src/assets/icons/game/bank.png' },
  { src: 'mob_app/img/ball_ps.png', dst: 'frontend/src/assets/icons/game/football.png' },
  { src: 'mob_app/img/chest_ps.png', dst: 'frontend/src/assets/icons/game/chest-wc2026.png' },
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

const root = path.join(__dirname, '..', '..');
const size = '256';

for (const item of pairs) {
  const src = path.resolve(root, item.src);
  const dst = path.resolve(root, item.dst);
  execFileSync(process.execPath, [script, src, dst, size], { stdio: 'inherit' });
}
