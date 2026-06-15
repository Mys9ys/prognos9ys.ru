const isLocalDev = ['localhost', '127.0.0.1'].includes(window.location.hostname)
    || window.location.hostname.endsWith('.loc');

const origin = window.location.origin;

export const baseConfig = {
    BASE_URL: isLocalDev ? `${origin}/mob_app/ajax/` : '/mob_app/ajax/',
    BITRIX_ACTION_URL: isLocalDev
        ? `${origin}/bitrix/services/main/ajax.php`
        : '/bitrix/services/main/ajax.php',
    USE_BITRIX_API: true,
};
