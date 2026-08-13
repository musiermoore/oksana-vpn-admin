const TOKEN_KEY = 'telegram-mini-app-token';
const TELEGRAM_USER_ID_KEY = 'telegram-mini-app-telegram-user-id';
const START_PARAM_KEY = 'telegram-mini-app-last-start-param';
const START_PARAM_AUTH_KEY = 'telegram-mini-app-last-auth-start-param';
const INIT_DATA_KEY = 'telegram-mini-app-last-init-data';
const BOOTSTRAP_DIAGNOSTIC_KEY = 'telegram-mini-app-last-bootstrap-diagnostic';
const INIT_DATA_RETRY_ATTEMPTS = 8;
const INIT_DATA_RETRY_DELAY_MS = 250;
const BOOTSTRAP_DIAGNOSTIC_TTL_MS = 120000;
const BOOTSTRAP_DIAGNOSTIC_URL = '/telegram-app/diagnostics/bootstrap';

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

const getTelegramLaunchParam = (key) => {
    const queryValue = new URLSearchParams(window.location.search).get(key);

    if (typeof queryValue === 'string' && queryValue.trim() !== '') {
        return queryValue;
    }

    const hash = window.location.hash?.replace(/^#/, '') ?? '';

    if (hash === '') {
        return null;
    }

    return new URLSearchParams(hash).get(key);
};

export const getTelegramStartParam = () => {
    const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param;

    if (typeof startParam === 'string' && startParam.trim() !== '') {
        return startParam.trim();
    }

    const queryStartParam = getTelegramLaunchParam('tgWebAppStartParam');

    return queryStartParam?.trim() || '';
};

export const getTelegramInitData = () => {
    const webAppInitData = window.Telegram?.WebApp?.initData?.trim() ?? '';

    if (webAppInitData !== '') {
        window.sessionStorage.setItem(INIT_DATA_KEY, webAppInitData);
        return webAppInitData;
    }

    const queryInitData = getTelegramLaunchParam('tgWebAppData')?.trim() ?? '';

    if (queryInitData !== '') {
        window.sessionStorage.setItem(INIT_DATA_KEY, queryInitData);
        return queryInitData;
    }

    return window.sessionStorage.getItem(INIT_DATA_KEY)?.trim() ?? '';
};

const truncate = (value, maxLength = 120) => {
    const normalized = String(value ?? '').trim();

    if (normalized.length <= maxLength) {
        return normalized;
    }

    return `${normalized.slice(0, maxLength)}...`;
};

const collectInitDataDiagnostic = () => {
    const sources = [
        ['web_app', window.Telegram?.WebApp?.initData?.trim() ?? ''],
        ['query', new URLSearchParams(window.location.search).get('tgWebAppData')?.trim() ?? ''],
        ['hash', new URLSearchParams(window.location.hash?.replace(/^#/, '') ?? '').get('tgWebAppData')?.trim() ?? ''],
        ['session', window.sessionStorage.getItem(INIT_DATA_KEY)?.trim() ?? ''],
    ];
    const [source, rawInitData] = sources.find(([, value]) => value !== '') ?? ['missing', ''];
    const parsed = new URLSearchParams(rawInitData);
    const userPayload = parsed.get('user');
    let initDataUserId = '';

    if (userPayload) {
        try {
            initDataUserId = String(JSON.parse(userPayload)?.id ?? '').trim();
        } catch {
            initDataUserId = '';
        }
    }

    return {
        source,
        length: rawInitData.length,
        keys: Array.from(new Set(Array.from(parsed.keys()))).slice(0, 20),
        userId: initDataUserId,
        authDate: truncate(parsed.get('auth_date'), 120) || null,
        queryIdPrefix: truncate(parsed.get('query_id'), 24) || null,
        hashPrefix: truncate(parsed.get('hash'), 12) || null,
    };
};

const getResolvedTimeZone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return '';
    }
};

const buildBootstrapDiagnosticPayload = ({ page, error, attempts, delayMs }) => {
    const initData = collectInitDataDiagnostic();

    return {
        page,
        error_message: truncate(error?.message ?? 'Unknown bootstrap error', 1000),
        error_name: truncate(error?.name ?? '', 255) || null,
        attempts,
        delay_ms: delayMs,
        href: truncate(window.location.href, 2000) || null,
        path: truncate(window.location.pathname, 500) || null,
        search: truncate(window.location.search, 2000) || null,
        referrer: truncate(window.document?.referrer, 2000) || null,
        user_agent: truncate(window.navigator?.userAgent, 2000) || null,
        timezone: truncate(getResolvedTimeZone(), 120) || null,
        language: truncate(window.navigator?.language, 40) || null,
        telegram_user_id: truncate(getTelegramProfileId(), 255) || null,
        telegram_start_param: truncate(getTelegramStartParam(), 255) || null,
        telegram_web_app_available: Boolean(window.Telegram?.WebApp),
        telegram_platform: truncate(window.Telegram?.WebApp?.platform, 255) || null,
        telegram_version: truncate(window.Telegram?.WebApp?.version, 255) || null,
        telegram_color_scheme: truncate(window.Telegram?.WebApp?.colorScheme, 255) || null,
        telegram_init_data_source: initData.source,
        telegram_init_data_length: initData.length,
        telegram_init_data_keys: initData.keys,
        telegram_init_data_user_id: truncate(initData.userId, 255) || null,
        telegram_init_data_auth_date: initData.authDate,
        telegram_init_data_query_id_prefix: initData.queryIdPrefix,
        telegram_init_data_hash_prefix: initData.hashPrefix,
        has_stored_token: getTelegramAppToken() !== '',
        stored_telegram_user_id: truncate(getTelegramAppTelegramUserId(), 255) || null,
    };
};

const shouldReportBootstrapDiagnostic = (payload) => {
    const signature = JSON.stringify([
        payload.page,
        payload.error_message,
        payload.path,
        payload.telegram_init_data_source,
        payload.telegram_user_id,
        payload.telegram_init_data_user_id,
    ]);
    const lastPayload = window.sessionStorage.getItem(BOOTSTRAP_DIAGNOSTIC_KEY);

    if (lastPayload) {
        try {
            const parsed = JSON.parse(lastPayload);

            if (parsed?.signature === signature && Number(parsed?.timestamp ?? 0) + BOOTSTRAP_DIAGNOSTIC_TTL_MS > Date.now()) {
                return false;
            }
        } catch {
            // Ignore malformed cache and allow the report.
        }
    }

    window.sessionStorage.setItem(BOOTSTRAP_DIAGNOSTIC_KEY, JSON.stringify({
        signature,
        timestamp: Date.now(),
    }));

    return true;
};

const postBootstrapDiagnosticPayload = async (payload) => {
    try {
        if (window.navigator?.sendBeacon && typeof Blob !== 'undefined') {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });

            if (window.navigator.sendBeacon(BOOTSTRAP_DIAGNOSTIC_URL, blob)) {
                return;
            }
        }
    } catch {
        // Continue with fetch/axios fallback.
    }

    try {
        if (window.fetch) {
            const response = await window.fetch(BOOTSTRAP_DIAGNOSTIC_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                return;
            }
        }
    } catch {
        // Continue with axios fallback.
    }

    await window.axios?.post(BOOTSTRAP_DIAGNOSTIC_URL, payload);
};

export const reportTelegramBootstrapDiagnostic = async ({ page, error, attempts = INIT_DATA_RETRY_ATTEMPTS, delayMs = INIT_DATA_RETRY_DELAY_MS }) => {
    try {
        const payload = buildBootstrapDiagnosticPayload({
            page,
            error,
            attempts,
            delayMs,
        });

        if (!shouldReportBootstrapDiagnostic(payload)) {
            return;
        }

        await postBootstrapDiagnosticPayload(payload);
    } catch {
        // Intentionally swallow diagnostic errors so bootstrap UX stays unchanged.
    }
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
    try {
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
    } catch (error) {
        await reportTelegramBootstrapDiagnostic({
            page: window.location.pathname || 'telegram-mini-app',
            error,
        });

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
