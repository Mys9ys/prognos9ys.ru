/**
 * Редиректы со старых query-вкладок профиля на отдельные маршруты.
 *
 * @param {{ path: string, query?: Record<string, string> }} to
 * @returns {{ path: string, query?: Record<string, string> }|null}
 */
export function resolveLegacyProfileRedirect(to) {
  if (to.path !== '/profile') {
    return null;
  }

  const tab = String(to.query?.tab || '');
  const eco = String(to.query?.eco || '');

  if (!tab && !eco) {
    return null;
  }

  const tabMap = {
    inventory: '/inventory',
    collection: '/collection',
    achievement: '/achievements',
    achievements: '/achievements',
    settings: '/settings',
    prognosis: '/prognosis',
  };

  if (tab === 'economy') {
    const ecoMap = {
      bank: '/bank',
      treasury: '/treasury',
      exchange: '/market',
      farm: '/work',
    };

    return { path: ecoMap[eco] || '/bank' };
  }

  if (tabMap[tab]) {
    return { path: tabMap[tab] };
  }

  return null;
}
