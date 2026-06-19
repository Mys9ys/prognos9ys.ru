import axios from 'axios';
import { baseConfig } from '@/store/config';

/**
 * Вызов Bitrix Engine Action (prognos9ys.main).
 * Ответ: { status: 'success', data: {...}, errors: [...] }
 */
export async function runBitrixAction(action, data = {}) {
    const params = { action, mode: 'class' };

    if (data.userToken) {
        params.userToken = data.userToken;
    } else if (data.token) {
        params.token = data.token;
    }

    const response = await axios.post(
        baseConfig.BITRIX_ACTION_URL,
        data,
        {
            params,
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

export async function fetchGameState(userToken) {
    if (!userToken) {
        throw new Error('Требуется авторизация');
    }

    try {
        return await runBitrixAction(
            'prognos9ys:main.GameController.getState',
            { userToken }
        );
    } catch (bitrixError) {
        const form = new FormData();
        form.append('userToken', userToken);

        const response = await axios.post(
            `${baseConfig.BASE_URL}game/state/`,
            form,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            }
        );

        if (response.data?.status === 'ok' && response.data?.game) {
            return { game: response.data.game };
        }

        throw bitrixError;
    }
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
        sendPrognosis: (userToken, fields, withBet = null) => runBitrixAction(
            'prognos9ys:main.FootballController.sendPrognosis',
            { userToken, fields, withBet: withBet === null ? undefined : withBet }
        ),
    },
    cs2: {
        getEventMatches: (events, userToken = '') => runBitrixAction(
            'prognos9ys:main.Cs2Controller.getEventMatches',
            { events, userToken }
        ),
        getMatch: (eventId, number, userToken = '') => runBitrixAction(
            'prognos9ys:main.Cs2Controller.getMatch',
            { eventId, number, userToken }
        ),
        sendPrognosis: (userToken, fields, mapScoresJson = '', withBet = null) => runBitrixAction(
            'prognos9ys:main.Cs2Controller.sendPrognosis',
            {
                userToken,
                fields,
                map_scores_json: mapScoresJson || undefined,
                withBet: withBet === null ? undefined : withBet,
            }
        ),
    },
    rating: {
        getFootball: (event, setId = null, userToken = '', options = {}) => runBitrixAction(
            'prognos9ys:main.RatingController.getFootballRatings',
            {
                event,
                setId: setId || undefined,
                userToken: userToken || undefined,
                selector: options.selector || undefined,
                limit: options.limit || undefined,
            }
        ),
        getRace: (events) => runBitrixAction(
            'prognos9ys:main.RatingController.getRaceRatings',
            { events }
        ),
    },
    ratingSet: {
        create: (userToken, payload) => runBitrixAction(
            'prognos9ys:main.RatingSetController.create',
            { userToken, ...payload }
        ),
        update: (userToken, setId, payload) => runBitrixAction(
            'prognos9ys:main.RatingSetController.update',
            { userToken, setId, ...payload }
        ),
        delete: (userToken, setId) => runBitrixAction(
            'prognos9ys:main.RatingSetController.delete',
            { userToken, setId }
        ),
        listMy: (userToken, sport = 'football', eventId = null) => runBitrixAction(
            'prognos9ys:main.RatingSetController.listMy',
            { userToken, sport, eventId: eventId || undefined }
        ),
        listPublic: (sport = 'football', eventId = null) => runBitrixAction(
            'prognos9ys:main.RatingSetController.listPublic',
            { sport, eventId: eventId || undefined }
        ),
        get: (setId, userToken = '') => runBitrixAction(
            'prognos9ys:main.RatingSetController.get',
            { setId, userToken: userToken || undefined }
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
    game: {
        getState: (userToken) => fetchGameState(userToken),
        claimXp: (userToken, matchId) => runBitrixAction(
            'prognos9ys:main.GameController.claimXp',
            { userToken, matchId }
        ),
        claimAllXp: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.claimAllXp',
            { userToken }
        ),
        getLevelTiers: () => runBitrixAction(
            'prognos9ys:main.GameController.getLevelTiers',
            {}
        ),
        getWealthRating: (limit = 30, wealthSort = 'rich') => runBitrixAction(
            'prognos9ys:main.GameController.getWealthRating',
            { limit, wealthSort }
        ),
        getGameBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getGameBank',
            { userToken }
        ),
        listBanks: (userToken, limit = 30) => runBitrixAction(
            'prognos9ys:main.GameController.listBanks',
            { userToken, limit }
        ),
        getMyBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getMyBank',
            { userToken }
        ),
        getMyContracts: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getMyContracts',
            { userToken }
        ),
        getBankOperations: (userToken, limit = 100) => runBitrixAction(
            'prognos9ys:main.GameController.getBankOperations',
            { userToken, limit }
        ),
        openBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.openBank',
            { userToken }
        ),
        createDeposit: (userToken, bankId, amount, eventId = 0) => runBitrixAction(
            'prognos9ys:main.GameController.createDeposit',
            { userToken, bankId, amount, eventId: eventId || undefined }
        ),
        takeLoan: (userToken, bankId, amount, eventId = 0) => runBitrixAction(
            'prognos9ys:main.GameController.takeLoan',
            { userToken, bankId, amount, eventId: eventId || undefined }
        ),
        cancelLoan: (userToken, loanId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelLoan',
            { userToken, loanId }
        ),
        cancelDeposit: (userToken, depositId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelDeposit',
            { userToken, depositId }
        ),
        closeBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.closeBank',
            { userToken }
        ),
        getAchievements: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getAchievements',
            { userToken }
        ),
        claimAchievement: (userToken, code) => runBitrixAction(
            'prognos9ys:main.GameController.claimAchievement',
            { userToken, code }
        ),
    },
    impersonation: {
        searchUsers: (userToken, query) => runBitrixAction(
            'prognos9ys:main.ImpersonationController.searchUsers',
            { userToken, query }
        ),
        start: (userToken, targetUserId) => runBitrixAction(
            'prognos9ys:main.ImpersonationController.start',
            { userToken, targetUserId }
        ),
        stop: (moderatorToken) => runBitrixAction(
            'prognos9ys:main.ImpersonationController.stop',
            { moderatorToken }
        ),
    },
};
