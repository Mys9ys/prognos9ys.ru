import axios from 'axios';
import { baseConfig } from '@/store/config';

/**
 * Вызов Bitrix Engine Action (prognos9ys.main).
 * Ответ: { status: 'success', data: {...}, errors: [...] }
 */
export async function runBitrixAction(action, data = {}) {
    const response = await axios.post(
        baseConfig.BITRIX_ACTION_URL,
        data,
        {
            params: { action, mode: 'class' },
            headers: { 'Content-Type': 'application/json' },
            withCredentials: true,
        }
    );

    const payload = response.data;

    if (payload.status === 'success') {
        return payload.data;
    }

    const message = payload.errors?.[0]?.message
        || payload.error
        || 'Ошибка запроса к API';

    throw new Error(message);
}

// Bitrix Engine: vendor:module.ControllerName.actionName (PascalCase + Controller suffix)
export const apiActions = {
    profile: {
        getPublic: (userId) => runBitrixAction('prognos9ys:main.ProfileController.getPublicProfile', { userId }),
        getMy: (userToken) => runBitrixAction('prognos9ys:main.ProfileController.getMyProfile', { userToken }),
    },
    football: {
        getEventMatches: (events, userToken = '') => runBitrixAction(
            'prognos9ys:main.FootballController.getEventMatches',
            { events, userToken }
        ),
        getMatchesByEvent: (eventId) => runBitrixAction(
            'prognos9ys:main.FootballController.getMatchesByEvent',
            { eventId }
        ),
        getMatch: (eventId, number, userToken = '') => runBitrixAction(
            'prognos9ys:main.FootballController.getMatch',
            { eventId, number, userToken }
        ),
        sendPrognosis: (userToken, fields) => runBitrixAction(
            'prognos9ys:main.FootballController.sendPrognosis',
            { userToken, fields }
        ),
    },
    rating: {
        getFootball: (event) => runBitrixAction(
            'prognos9ys:main.RatingController.getFootballRatings',
            { event }
        ),
        getRace: (events) => runBitrixAction(
            'prognos9ys:main.RatingController.getRaceRatings',
            { events }
        ),
    },
    championship: {
        getFootballTable: (events, token = '') => runBitrixAction(
            'prognos9ys:main.ChampionshipController.getFootballTable',
            { events, token }
        ),
    },
    catalog: {
        getEvents: (type) => runBitrixAction(
            'prognos9ys:main.CatalogController.getEvents',
            { type }
        ),
    },
};
