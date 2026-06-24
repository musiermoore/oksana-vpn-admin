const TOKEN_KEY = 'telegram-mini-app-token';

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
