const TOKEN_KEY = 'telegram-mini-app-token';
const TELEGRAM_USER_ID_KEY = 'telegram-mini-app-telegram-user-id';
const START_PARAM_KEY = 'telegram-mini-app-last-start-param';
const START_PARAM_AUTH_KEY = 'telegram-mini-app-last-auth-start-param';
const INIT_DATA_KEY = 'telegram-mini-app-last-init-data';
const INIT_DATA_RETRY_ATTEMPTS = 3;
const INIT_DATA_RETRY_DELAY_MS = 250;

export const telegramAppLabels = {
    open: 'Открыт',
    answered: 'Есть ответ',
    closed: 'Закрыт',
};

export const getTelegramAppToken = () => window.localStorage.getItem(TOKEN_KEY) ?? '';

export const getTelegramAppTelegramUserId = () => window.localStorage.getItem(TELEGRAM_USER_ID_KEY) ?? '';

export const setTelegramAppToken = (token) => {
    if (token) {
        window.localStorage.setItem(TOKEN_KEY, token);
        return;
    }

    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(TELEGRAM_USER_ID_KEY);
};

export const setTelegramAppTelegramUserId = (telegramUserId) => {
    if (telegramUserId) {
        window.localStorage.setItem(TELEGRAM_USER_ID_KEY, telegramUserId);
        return;
    }

    window.localStorage.removeItem(TELEGRAM_USER_ID_KEY);
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

export const getTelegramProfileId = () => {
    const telegramUserId = getTelegramProfile()?.id;

    return telegramUserId === undefined || telegramUserId === null
        ? ''
        : String(telegramUserId).trim();
};

export const getTelegramStartParam = () => {
    const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param;

    if (typeof startParam === 'string' && startParam.trim() !== '') {
        return startParam.trim();
    }

    const queryStartParam = new URLSearchParams(window.location.search).get('tgWebAppStartParam');

    return queryStartParam?.trim() || '';
};

export const getTelegramInitData = () => {
    const webAppInitData = window.Telegram?.WebApp?.initData?.trim() ?? '';

    if (webAppInitData !== '') {
        window.sessionStorage.setItem(INIT_DATA_KEY, webAppInitData);
        return webAppInitData;
    }

    const queryInitData = new URLSearchParams(window.location.search).get('tgWebAppData')?.trim() ?? '';

    if (queryInitData !== '') {
        window.sessionStorage.setItem(INIT_DATA_KEY, queryInitData);
        return queryInitData;
    }

    return window.sessionStorage.getItem(INIT_DATA_KEY)?.trim() ?? '';
};

export const isReferralStartParam = (value) => /^ref_\d+$/.test((value ?? '').trim());

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
    const initData = getTelegramInitData();

    if (initData === '') {
        throw new Error('Откройте приложение через Telegram.');
    }

    return initData;
};

const wait = (delayMs) => new Promise((resolve) => {
    window.setTimeout(resolve, delayMs);
});

export const resolveTelegramInitData = async (attempts = INIT_DATA_RETRY_ATTEMPTS, delayMs = INIT_DATA_RETRY_DELAY_MS) => {
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        const initData = getTelegramInitData();

        if (initData !== '') {
            return initData;
        }

        if (attempt < attempts - 1) {
            await wait(delayMs);
        }
    }

    throw new Error('Откройте приложение через Telegram.');
};

export const loginTelegramApp = async (authUrl) => {
    const initData = await resolveTelegramInitData();
    const response = await window.axios.post(authUrl, {
        init_data: initData,
    });

    const token = response?.data?.token ?? '';

    if (token === '') {
        throw new Error('Не удалось выполнить вход через Telegram.');
    }

    setTelegramAppToken(token);
    setTelegramAppTelegramUserId(getTelegramProfileId());

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
    const startParam = getTelegramStartParam();
    const lastAuthStartParam = window.sessionStorage.getItem(START_PARAM_AUTH_KEY) ?? '';
    const currentTelegramUserId = getTelegramProfileId();
    const storedTelegramUserId = getTelegramAppTelegramUserId();
    const shouldRefreshForReferral = isReferralStartParam(startParam) && lastAuthStartParam !== startParam;
    const shouldRefreshForMissingTelegramUser =
        getTelegramAppToken() !== '' && currentTelegramUserId !== '' && storedTelegramUserId === '';
    const shouldRefreshForTelegramUser = currentTelegramUserId !== '' && storedTelegramUserId !== '' && storedTelegramUserId !== currentTelegramUserId;

    if (getTelegramAppToken() === '' || shouldRefreshForReferral || shouldRefreshForMissingTelegramUser || shouldRefreshForTelegramUser) {
        await loginTelegramApp(authUrl);

        if (shouldRefreshForReferral) {
            window.sessionStorage.setItem(START_PARAM_AUTH_KEY, startParam);
        }
    }

    try {
        return await fetchTelegramAppProfile(profileUrl);
    } catch (error) {
        if (error?.response?.status === 401) {
            await loginTelegramApp(authUrl);

            if (isReferralStartParam(startParam)) {
                window.sessionStorage.setItem(START_PARAM_AUTH_KEY, startParam);
            }

            return await fetchTelegramAppProfile(profileUrl);
        }

        throw error;
    }
};

export const normalizeTelegramAppError = (error, fallback = 'Что-то пошло не так.') => {
    return error?.response?.data?.message ?? error?.message ?? fallback;
};

export const isTelegramDebtError = (error) => error?.response?.data?.type === 'debt';

export const fetchTelegramBinary = async (url) => {
    return await window.axios.get(url, {
        headers: telegramAppHeaders(),
        responseType: 'blob',
    });
};

export const getFilenameFromDisposition = (headerValue, fallback = 'download.bin') => {
    const header = String(headerValue ?? '');
    const utf8Match = header.match(/filename\*=UTF-8''([^;]+)/i);

    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1]);
    }

    const simpleMatch = header.match(/filename="?([^"]+)"?/i);

    return simpleMatch?.[1] ?? fallback;
};

export const triggerBrowserDownload = (blob, filename) => {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = objectUrl;
    link.download = filename;
    link.click();

    window.setTimeout(() => {
        URL.revokeObjectURL(objectUrl);
    }, 1000);
};

export const openTelegramExternalLink = (url) => {
    if (!url) {
        return;
    }

    if (window.Telegram?.WebApp?.openLink && /^https?:\/\//i.test(url)) {
        window.Telegram.WebApp.openLink(url);
        return;
    }

    window.location.href = url;
};
