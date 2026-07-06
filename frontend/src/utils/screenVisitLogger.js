import { apiActions } from '@/api/bitrixClient';
import store from '@/store';

let lastLoggedPath = '';

/**
 * Логирует открытие экрана (роут mob_app) — fire-and-forget.
 * @param {import('vue-router').RouteLocationNormalized} to
 */
export function logScreenVisit(to) {
    if (!to || to.meta?.skipVisitLog) {
        return;
    }

    const screen = to.fullPath || to.path || '/';
    if (!screen || screen === lastLoggedPath) {
        return;
    }
    lastLoggedPath = screen;

    const token = store.state.auth?.authData?.token || '';
    const referrer = typeof document !== 'undefined' ? (document.referrer || '') : '';

    apiActions.analytics.logScreenVisit(screen, token, referrer).catch(() => {
        // тихо — аналитика не должна мешать навигации
    });
}
