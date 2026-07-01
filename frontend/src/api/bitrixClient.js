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

export async function fetchGameState(userToken, options = {}) {
    if (!userToken) {
        throw new Error('Требуется авторизация');
    }

    const withGrants = Boolean(options.withGrants);
    const refresh = Boolean(options.refresh);

    try {
        return await runBitrixAction(
            'prognos9ys:main.GameController.getState',
            { userToken, withGrants, refresh }
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
        getEventStatistics: (events, userToken = '') => runBitrixAction(
            'prognos9ys:main.FootballController.getEventStatistics',
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
        randomPrognosis: (userToken, matchId) => runBitrixAction(
            'prognos9ys:main.FootballController.randomPrognosis',
            { userToken, matchId }
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
        getMaps: () => runBitrixAction('prognos9ys:main.Cs2Controller.getMaps', {}),
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
        getState: (userToken, options = {}) => fetchGameState(userToken, options),
        claimXp: (userToken, matchId) => runBitrixAction(
            'prognos9ys:main.GameController.claimXp',
            { userToken, matchId }
        ),
        claimAllXp: (userToken, targetUserId = 0) => runBitrixAction(
            'prognos9ys:main.GameController.claimAllXp',
            { userToken, targetUserId: targetUserId || undefined }
        ),
        getLevelTiers: () => runBitrixAction(
            'prognos9ys:main.GameController.getLevelTiers',
            {}
        ),
        getWealthRating: (limit = 30, wealthSort = 'rich', offset = 0) => runBitrixAction(
            'prognos9ys:main.GameController.getWealthRating',
            { limit, wealthSort, offset }
        ),
        getGameBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getGameBank',
            { userToken }
        ),
        getTreasury: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getTreasury',
            { userToken }
        ),
        getTreasuryLaborOrders: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getTreasuryLaborOrders',
            { userToken }
        ),
        createTreasuryLaborOrder: (userToken, professionCode, iterations, payPerCycle) => runBitrixAction(
            'prognos9ys:main.GameController.createTreasuryLaborOrder',
            {
                userToken,
                professionCode,
                iterations,
                payPerCycle,
            }
        ),
        cancelTreasuryLaborOrder: (userToken, orderId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelTreasuryLaborOrder',
            { userToken, orderId }
        ),
        listTreasuryGovMaterial: (userToken, materialCode, qty) => runBitrixAction(
            'prognos9ys:main.GameController.listTreasuryGovMaterial',
            { userToken, materialCode, qty }
        ),
        cancelTreasuryGovListing: (userToken, listingId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelTreasuryGovListing',
            { userToken, listingId }
        ),
        getTreasuryShop: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getTreasuryShop',
            { userToken }
        ),
        buyTreasuryChest: (userToken, currency, targetUserId = 0, milestone = 0) => runBitrixAction(
            'prognos9ys:main.GameController.buyTreasuryChest',
            { userToken, currency, targetUserId: targetUserId || undefined, milestone: milestone || undefined }
        ),
        buyTreasuryPremium: (userToken, offerKey = 'premium_1d', targetUserId = 0, milestone = 0) => runBitrixAction(
            'prognos9ys:main.GameController.buyTreasuryPremium',
            {
                userToken,
                offerKey,
                targetUserId: targetUserId || undefined,
                milestone: milestone || undefined,
            }
        ),
        createGovSupportDeposit: (userToken, bankId, eventId = 0, amount = 0) => runBitrixAction(
            'prognos9ys:main.GameController.createGovSupportDeposit',
            { userToken, bankId, eventId: eventId || undefined, amount: amount || undefined }
        ),
        closeGovSupportDeposit: (userToken, depositId) => runBitrixAction(
            'prognos9ys:main.GameController.closeGovSupportDeposit',
            { userToken, depositId }
        ),
        getGovSupportDeposits: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getGovSupportDeposits',
            { userToken }
        ),
        getFarmState: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getFarmState',
            { userToken }
        ),
        pickFarmProfessions: (userToken, codes) => runBitrixAction(
            'prognos9ys:main.GameController.pickFarmProfessions',
            {
                userToken,
                professions: (Array.isArray(codes) ? codes : []).filter(Boolean).join(','),
            }
        ),
        startFarmWork: (userToken, professionCode, workMode = 'treasury', iterations = 0) => runBitrixAction(
            'prognos9ys:main.GameController.startFarmWork',
            { userToken, professionCode, workMode, iterations }
        ),
        cancelFarmWork: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.cancelFarmWork',
            { userToken }
        ),
        enqueuePremiumWork: (userToken, taskType, payload) => runBitrixAction(
            'prognos9ys:main.GameController.enqueuePremiumWork',
            { userToken, taskType, payload: JSON.stringify(payload || {}) }
        ),
        enqueuePremiumMacro: (userToken, macroType, options) => runBitrixAction(
            'prognos9ys:main.GameController.enqueuePremiumMacro',
            { userToken, macroType, options: JSON.stringify(options || {}) }
        ),
        updatePremiumWorkSellMode: (userToken, taskId, sellMode) => runBitrixAction(
            'prognos9ys:main.GameController.updatePremiumWorkSellMode',
            { userToken, taskId, sellMode }
        ),
        cancelPremiumWork: (userToken, taskId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelPremiumWork',
            { userToken, taskId }
        ),
        getAlbumState: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getAlbumState',
            { userToken }
        ),
        craftAlbums: (userToken, professionCode) => runBitrixAction(
            'prognos9ys:main.GameController.craftAlbums',
            { userToken, professionCode }
        ),
        activateAlbum: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.activateAlbum',
            { userToken }
        ),
        glueAlbumItem: (userToken, albumId, itemCode) => runBitrixAction(
            'prognos9ys:main.GameController.glueAlbumItem',
            { userToken, albumId, itemCode }
        ),
        glueAllAlbumItems: (userToken, albumId = 0) => runBitrixAction(
            'prognos9ys:main.GameController.glueAllAlbumItems',
            { userToken, albumId: albumId || undefined }
        ),
        buyAlbumCollectionToTier: (userToken, collection, targetTier) => runBitrixAction(
            'prognos9ys:main.GameController.buyAlbumCollectionToTier',
            { userToken, collection, targetTier }
        ),
        moderatorBulkAction: (userToken, bulkAction) => runBitrixAction(
            'prognos9ys:main.GameController.moderatorBulkAction',
            { userToken, bulkAction }
        ),
        moderatorBulkCandidates: (userToken, bulkAction) => runBitrixAction(
            'prognos9ys:main.GameController.moderatorBulkCandidates',
            { userToken, bulkAction }
        ),
        moderatorBulkRunOne: (userToken, bulkAction, targetUserId) => runBitrixAction(
            'prognos9ys:main.GameController.moderatorBulkRunOne',
            { userToken, bulkAction, targetUserId }
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
        getBankOperations: (userToken, limit = 30) => runBitrixAction(
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
        repayLoan: (userToken, loanId) => runBitrixAction(
            'prognos9ys:main.GameController.repayLoan',
            { userToken, loanId }
        ),
        repayAllLoans: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.repayAllLoans',
            { userToken }
        ),
        cancelDeposit: (userToken, depositId) => runBitrixAction(
            'prognos9ys:main.GameController.cancelDeposit',
            { userToken, depositId }
        ),
        forceCloseDeposit: (userToken, depositId) => runBitrixAction(
            'prognos9ys:main.GameController.forceCloseDeposit',
            { userToken, depositId }
        ),
        closeBank: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.closeBank',
            { userToken }
        ),
        updateBankConsignmentSettings: (userToken, enabled, categoriesJson = '') => runBitrixAction(
            'prognos9ys:main.GameController.updateBankConsignmentSettings',
            { userToken, enabled: enabled ? 1 : 0, categoriesJson }
        ),
        getAchievements: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getAchievements',
            { userToken }
        ),
        claimAchievement: (userToken, code) => runBitrixAction(
            'prognos9ys:main.GameController.claimAchievement',
            { userToken, code }
        ),
        openWc26Chests: (userToken, openAll = false) => runBitrixAction(
            'prognos9ys:main.GameController.openWc26Chests',
            { userToken, openAll: openAll ? 1 : 0 }
        ),
        openChests: (userToken, pool, openAll = false) => runBitrixAction(
            'prognos9ys:main.GameController.openChests',
            { userToken, pool, openAll: openAll ? 1 : 0 }
        ),
        openXpBanks: (userToken, code, openAll = false) => runBitrixAction(
            'prognos9ys:main.GameController.openXpBanks',
            { userToken, code, openAll: openAll ? 1 : 0 }
        ),
        activateProfessionCertificate: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.activateProfessionCertificate',
            { userToken }
        ),
        activatePremiumScroll: (userToken, days = 0, activateAll = false) => runBitrixAction(
            'prognos9ys:main.GameController.activatePremiumScroll',
            { userToken, days, activateAll: activateAll ? 1 : 0 }
        ),
        learnAlbumRecipe: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.learnAlbumRecipe',
            { userToken }
        ),
        openLootPacks: (userToken, code, openAll = false) => runBitrixAction(
            'prognos9ys:main.GameController.openLootPacks',
            { userToken, code, openAll: openAll ? 1 : 0 }
        ),
        getChestOpenLogMeta: (userToken) => runBitrixAction(
            'prognos9ys:main.GameController.getChestOpenLogMeta',
            { userToken }
        ),
        getChestOpenLogs: (userToken, eventId = 0, groupKey = 'all', offset = 0, limit = 25) => runBitrixAction(
            'prognos9ys:main.GameController.getChestOpenLogs',
            {
                userToken,
                eventId: eventId || undefined,
                groupKey: groupKey || 'all',
                offset,
                limit,
            }
        ),
    },
    exchange: {
        getState: (userToken) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getState',
            { userToken }
        ),
        getCatalog: (userToken, offset = 0, limit = 25, catalogTab = '', search = '', qtySort = '') => runBitrixAction(
            'prognos9ys:main.ExchangeController.getCatalog',
            {
                userToken,
                offset,
                limit,
                catalogTab: catalogTab || undefined,
                search: search || undefined,
                qtySort: qtySort || undefined,
            }
        ),
        getMyListings: (userToken) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getMyListings',
            { userToken }
        ),
        createListing: (userToken, kind, code, qty, pricePerUnit, category = '', eventId = 0, teamCode = '') => runBitrixAction(
            'prognos9ys:main.ExchangeController.createListing',
            {
                userToken,
                kind,
                code,
                qty,
                pricePerUnit,
                category: category || undefined,
                eventId: eventId || undefined,
                teamCode: teamCode || undefined,
            }
        ),
        cancelListing: (userToken, listingId) => runBitrixAction(
            'prognos9ys:main.ExchangeController.cancelListing',
            { userToken, listingId }
        ),
        buy: (userToken, kind, code, qty, category = '', eventId = 0, teamCode = '', pricePerUnit = 0) => runBitrixAction(
            'prognos9ys:main.ExchangeController.buy',
            {
                userToken,
                kind,
                code,
                qty,
                category: category || undefined,
                eventId: eventId || undefined,
                teamCode: teamCode || undefined,
                pricePerUnit: pricePerUnit > 0 ? pricePerUnit : undefined,
            }
        ),
        getTradeHistory: (userToken, offset = 0, limit = 25) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getTradeHistory',
            { userToken, offset, limit }
        ),
        consignToBank: (userToken, kind, code, qty, category = '', eventId = 0, teamCode = '') => runBitrixAction(
            'prognos9ys:main.ExchangeController.consignToBank',
            {
                userToken,
                kind,
                code,
                qty,
                category: category || undefined,
                eventId: eventId || undefined,
                teamCode: teamCode || undefined,
            }
        ),
        getDuplicateSouvenirPlan: (userToken) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getDuplicateSouvenirPlan',
            { userToken }
        ),
        bulkSellDuplicateSouvenirs: (userToken, sellMode, pricePerUnit = 0) => runBitrixAction(
            'prognos9ys:main.ExchangeController.bulkSellDuplicateSouvenirs',
            { userToken, sellMode, pricePerUnit: pricePerUnit > 0 ? pricePerUnit : undefined }
        ),
        getLaborState: (userToken) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getLaborState',
            { userToken }
        ),
        getLaborOrders: (userToken, offset = 0, limit = 25) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getLaborOrders',
            { userToken, offset, limit }
        ),
        getMyLaborOrders: (userToken) => runBitrixAction(
            'prognos9ys:main.ExchangeController.getMyLaborOrders',
            { userToken }
        ),
        createLaborOrder: (userToken, professionCode, iterations, payPerCycle) => runBitrixAction(
            'prognos9ys:main.ExchangeController.createLaborOrder',
            {
                userToken,
                professionCode,
                iterations,
                payPerCycle,
            }
        ),
        cancelLaborOrder: (userToken, orderId) => runBitrixAction(
            'prognos9ys:main.ExchangeController.cancelLaborOrder',
            { userToken, orderId }
        ),
        claimLaborOrder: (userToken, orderId, iterations = 0) => runBitrixAction(
            'prognos9ys:main.ExchangeController.claimLaborOrder',
            { userToken, orderId, iterations: iterations > 0 ? iterations : undefined }
        ),
        startLaborWorkshop: (userToken, orderId, iterations = 0) => runBitrixAction(
            'prognos9ys:main.ExchangeController.startLaborWorkshop',
            { userToken, orderId, iterations: iterations > 0 ? iterations : undefined }
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
