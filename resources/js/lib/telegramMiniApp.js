const TOKEN_KEY = 'telegram-mini-app-token';
const START_PARAM_KEY = 'telegram-mini-app-last-start-param';

export const telegramAppLabels = {
    open: 'Открыт',
    answered: 'Есть ответ',
    closed: 'Закрыт',
};

export const getTelegramAppToken = () => window.localStorage.getItem(TOKEN_KEY) ?? '';

export const setTelegramAppToken = (token) => {
    if (token) {
        window.localStorage.setItem(TOKEN_KEY, token);
        return;
    }

    window.localStorage.removeItem(TOKEN_KEY);
};

export const telegramAppHeaders = () => {
    const token = getTelegramAppToken();

    return token ? { Authorization: `Bearer ${token}` } : {};
};

export const prepareTelegramWebApp = () => {
    if (!window.Telegram?.WebApp) {
        return null;
    }

    window.Telegram.WebApp.ready();
    window.Telegram.WebApp.expand();

    return window.Telegram.WebApp;
};

export const getTelegramProfile = () => {
    return window.Telegram?.WebApp?.initDataUnsafe?.user ?? null;
};

export const getTelegramStartParam = () => {
    const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param;

    if (typeof startParam === 'string' && startParam.trim() !== '') {
        return startParam.trim();
    }

    const queryStartParam = new URLSearchParams(window.location.search).get('tgWebAppStartParam');

    return queryStartParam?.trim() || '';
};

export const redirectFromTelegramStartParam = (routes) => {
    const startParam = getTelegramStartParam();

    if (startParam === '') {
        return false;
    }

    const lastConsumedStartParam = window.sessionStorage.getItem(START_PARAM_KEY) ?? '';
    const ticketMatch = startParam.match(/^ticket_(\d+)$/);

    if (!ticketMatch) {
        window.sessionStorage.setItem(START_PARAM_KEY, startParam);
        return false;
    }

    const targetUrl = `${routes?.support}/${ticketMatch[1]}`;
    const currentPath = window.location.pathname.replace(/\/+$/, '');
    const targetPath = new URL(targetUrl, window.location.origin).pathname.replace(/\/+$/, '');

    if (currentPath === targetPath) {
        window.sessionStorage.setItem(START_PARAM_KEY, startParam);
        return false;
    }

    if (lastConsumedStartParam === startParam) {
        return false;
    }

    window.sessionStorage.setItem(START_PARAM_KEY, startParam);
    window.location.replace(targetUrl);

    return true;
};

export const requireTelegramInitData = () => {
    const initData = window.Telegram?.WebApp?.initData ?? '';

    if (initData === '') {
        throw new Error('Откройте приложение через Telegram.');
    }

    return initData;
};

export const loginTelegramApp = async (authUrl) => {
    const initData = requireTelegramInitData();
    const response = await window.axios.post(authUrl, {
        init_data: initData,
    });

    const token = response?.data?.token ?? '';

    if (token === '') {
        throw new Error('Не удалось выполнить вход через Telegram.');
    }

    setTelegramAppToken(token);

    return response.data;
};

export const fetchTelegramAppProfile = async (profileUrl) => {
    const response = await window.axios.get(profileUrl, {
        headers: telegramAppHeaders(),
    });

    return response.data.user;
};

export const ensureTelegramAppSession = async ({ authUrl, profileUrl }) => {
    prepareTelegramWebApp();

    if (getTelegramAppToken() === '') {
        await loginTelegramApp(authUrl);
    }

    try {
        return await fetchTelegramAppProfile(profileUrl);
    } catch (error) {
        if (error?.response?.status === 401) {
            await loginTelegramApp(authUrl);

            return await fetchTelegramAppProfile(profileUrl);
        }

        throw error;
    }
};

export const normalizeTelegramAppError = (error, fallback = 'Что-то пошло не так.') => {
    return error?.response?.data?.message ?? error?.message ?? fallback;
};
