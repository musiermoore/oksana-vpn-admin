import test from 'node:test';
import assert from 'node:assert/strict';

import {
    ensureTelegramAppSession,
    getTelegramInitData,
    getTelegramStartParam,
    loginTelegramAppAndRedirect,
    redirectFromTelegramStartParam,
    reportTelegramBootstrapDiagnostic,
    requireTelegramInitData,
    resolveTelegramInitData,
} from './telegramMiniApp.js';

class MemoryStorage {
    constructor() {
        this.store = new Map();
    }

    getItem(key) {
        return this.store.has(key) ? this.store.get(key) : null;
    }

    setItem(key, value) {
        this.store.set(key, String(value));
    }

    removeItem(key) {
        this.store.delete(key);
    }
}

const createWindow = ({
    initData = '',
    search = '',
    hash = '',
    storedInitData = '',
    pathname = '/telegram-app/',
    telegramUser = {},
} = {}) => {
    const sessionStorage = new MemoryStorage();
    const location = {
        href: `https://example.com${pathname}${search}`,
        origin: 'https://example.com',
        pathname,
        search,
        hash,
        replacedWith: null,
        replace(url) {
            this.replacedWith = url;
        },
    };

    if (storedInitData !== '') {
        sessionStorage.setItem('telegram-mini-app-last-init-data', storedInitData);
    }

    return {
        Telegram: {
            WebApp: {
                initData,
                initDataUnsafe: {
                    user: telegramUser,
                },
            },
        },
        axios: {
            post: async () => ({ data: { message: 'ok' } }),
            get: async () => ({ data: { user: { id: 1, name: 'Alice' } } }),
        },
        navigator: {
            userAgent: 'Telegram-WebApp-Test',
            language: 'ru-RU',
        },
        location,
        document: {
            referrer: 'https://t.me/oksanavpn_bot',
        },
        localStorage: new MemoryStorage(),
        sessionStorage,
        setTimeout,
    };
};

test('getTelegramInitData uses Telegram WebApp initData and stores it in session', () => {
    global.window = createWindow({
        initData: 'query_id=abc&user=%7B%7D',
    });

    assert.equal(getTelegramInitData(), 'query_id=abc&user=%7B%7D');
    assert.equal(window.sessionStorage.getItem('telegram-mini-app-last-init-data'), 'query_id=abc&user=%7B%7D');
});

test('getTelegramInitData falls back to tgWebAppData query parameter', () => {
    global.window = createWindow({
        search: '?tgWebAppData=query_id%3Dabc%26user%3D%257B%257D',
    });

    assert.equal(getTelegramInitData(), 'query_id=abc&user=%7B%7D');
    assert.equal(window.sessionStorage.getItem('telegram-mini-app-last-init-data'), 'query_id=abc&user=%7B%7D');
});

test('getTelegramInitData falls back to tgWebAppData hash parameter', () => {
    global.window = createWindow({
        hash: '#tgWebAppData=query_id%3Dhash%26user%3D%257B%257D',
    });

    assert.equal(getTelegramInitData(), 'query_id=hash&user=%7B%7D');
    assert.equal(window.sessionStorage.getItem('telegram-mini-app-last-init-data'), 'query_id=hash&user=%7B%7D');
});

test('requireTelegramInitData falls back to initData stored in session', () => {
    global.window = createWindow({
        storedInitData: 'query_id=stored&user=%7B%7D',
    });

    assert.equal(requireTelegramInitData(), 'query_id=stored&user=%7B%7D');
});

test('requireTelegramInitData throws when no initData source is available', () => {
    global.window = createWindow();

    assert.throws(() => requireTelegramInitData(), {
        message: 'Откройте приложение через Telegram.',
    });
});

test('resolveTelegramInitData retries until Telegram WebApp initData becomes available', async () => {
    global.window = createWindow();

    setTimeout(() => {
        window.Telegram.WebApp.initData = 'query_id=late&user=%7B%7D';
    }, 10);

    await assert.doesNotReject(async () => {
        const initData = await resolveTelegramInitData(3, 20);
        assert.equal(initData, 'query_id=late&user=%7B%7D');
    });
});

test('resolveTelegramInitData throws after exhausting retries', async () => {
    global.window = createWindow();

    await assert.rejects(resolveTelegramInitData(3, 1), {
        message: 'Откройте приложение через Telegram.',
    });
});

test('getTelegramStartParam falls back to tgWebAppStartParam query parameter', () => {
    global.window = createWindow({
        search: '?tgWebAppStartParam=ticket_42',
    });

    assert.equal(getTelegramStartParam(), 'ticket_42');
});

test('getTelegramStartParam falls back to tgWebAppStartParam hash parameter', () => {
    global.window = createWindow({
        hash: '#tgWebAppStartParam=ticket_43',
    });

    assert.equal(getTelegramStartParam(), 'ticket_43');
});

test('redirectFromTelegramStartParam routes payments start param to payments page', () => {
    global.window = createWindow({
        search: '?tgWebAppStartParam=payments',
    });

    const redirected = redirectFromTelegramStartParam({
        payments: 'https://example.com/telegram-app/payments',
        support: 'https://example.com/telegram-app/support',
    });

    assert.equal(redirected, true);
    assert.equal(window.location.replacedWith, 'https://example.com/telegram-app/payments');
    assert.equal(window.sessionStorage.getItem('telegram-mini-app-last-start-param'), 'payments');
});

test('ensureTelegramAppSession redirects public app users without token to public login', async () => {
    global.window = createWindow({
        pathname: '/public/',
    });

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.location.replacedWith, '/public/login');
});

test('loginTelegramAppAndRedirect stores Telegram token and redirects home with valid initData', async () => {
    const requests = [];

    global.window = createWindow({
        initData: 'query_id=abc&user=%7B%7D',
        pathname: '/public/login',
    });
    window.axios.post = async (url, payload) => {
        requests.push({ url, payload });

        return {
            data: {
                token: 'telegram-token',
                user: {
                    telegram_id: '123456789',
                },
            },
        };
    };

    const data = await loginTelegramAppAndRedirect({
        authUrl: '/public/auth/telegram',
        homeUrl: '/public',
        attempts: 1,
        delayMs: 1,
    });

    assert.equal(data.token, 'telegram-token');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/public/auth/telegram');
    assert.deepEqual(requests[0].payload, { init_data: 'query_id=abc&user=%7B%7D' });
    assert.equal(window.localStorage.getItem('telegram-mini-app-token'), 'telegram-token');
    assert.equal(window.localStorage.getItem('telegram-mini-app-telegram-user-id'), '123456789');
    assert.equal(window.location.href, '/public');
});

test('loginTelegramAppAndRedirect shows missing initData as an error and does not redirect', async () => {
    const requests = [];

    global.window = createWindow({
        pathname: '/public/login',
    });
    window.axios.post = async (url, payload) => {
        requests.push({ url, payload });

        return { data: { token: 'telegram-token' } };
    };

    await assert.rejects(loginTelegramAppAndRedirect({
        authUrl: '/public/auth/telegram',
        homeUrl: '/public',
        attempts: 1,
        delayMs: 1,
    }), {
        message: 'Откройте приложение через Telegram.',
    });

    assert.equal(requests.length, 0);
    assert.equal(window.localStorage.getItem('telegram-mini-app-token'), null);
    assert.equal(window.location.href, 'https://example.com/public/login');
    assert.equal(window.location.replacedWith, null);
});

test('ensureTelegramAppSession redirects hidden public app users without token to root login', async () => {
    global.window = createWindow({
        pathname: '/',
    });

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/auth/telegram',
        profileUrl: '/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.location.replacedWith, '/login');
});

test('ensureTelegramAppSession redirects hidden public app users to root login even with internal public profile url', async () => {
    global.window = createWindow({
        pathname: '/',
    });

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.location.replacedWith, '/login');
});

test('ensureTelegramAppSession does not redirect root public login page', async () => {
    global.window = createWindow({
        pathname: '/login',
    });

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.location.replacedWith, null);
});

test('ensureTelegramAppSession does not redirect internal public login page', async () => {
    global.window = createWindow({
        pathname: '/public/login',
    });

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.location.replacedWith, null);
});

test('ensureTelegramAppSession loads public app profile when token exists', async () => {
    const requests = [];

    global.window = createWindow({
        pathname: '/public/',
    });
    window.localStorage.setItem('telegram-mini-app-token', 'plain-token');
    window.axios.get = async (url, options) => {
        requests.push({ url, options });

        return { data: { user: { id: 1, name: 'Alice' } } };
    };

    const user = await ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    });

    assert.deepEqual(user, { id: 1, name: 'Alice' });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/public/me');
    assert.equal(requests[0].options.headers.Authorization, 'Bearer plain-token');
    assert.equal(window.location.replacedWith, null);
});

test('ensureTelegramAppSession clears token and redirects public app users after 401', async () => {
    global.window = createWindow({
        pathname: '/public/payments',
    });
    window.localStorage.setItem('telegram-mini-app-token', 'expired-token');
    window.axios.get = async () => {
        const error = new Error('Unauthorized');
        error.response = { status: 401 };

        throw error;
    };

    await assert.rejects(ensureTelegramAppSession({
        authUrl: '/public/auth/telegram',
        profileUrl: '/public/me',
    }), {
        message: 'Требуется вход.',
    });

    assert.equal(window.localStorage.getItem('telegram-mini-app-token'), null);
    assert.equal(window.location.replacedWith, '/public/login');
});

test('reportTelegramBootstrapDiagnostic sends a deduplicated diagnostic payload', async () => {
    const requests = [];

    global.window = createWindow({
        search: '?tgWebAppData=query_id%3Dabc%26auth_date%3D123%26hash%3Dabcdef123456%26user%3D%257B%2522id%2522%253A777%257D&tgWebAppStartParam=ticket_42',
    });
    global.window.axios.post = async (url, payload) => {
        requests.push({ url, payload });
        return { data: { message: 'ok' } };
    };

    await reportTelegramBootstrapDiagnostic({
        page: '/telegram-app/',
        error: new Error('Откройте приложение через Telegram.'),
        attempts: 3,
        delayMs: 250,
    });
    await reportTelegramBootstrapDiagnostic({
        page: '/telegram-app/',
        error: new Error('Откройте приложение через Telegram.'),
        attempts: 3,
        delayMs: 250,
    });

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/telegram-app/diagnostics/bootstrap');
    assert.equal(requests[0].payload.telegram_init_data_source, 'query');
    assert.equal(requests[0].payload.telegram_init_data_user_id, '777');
    assert.equal(requests[0].payload.telegram_start_param, 'ticket_42');
    assert.deepEqual(requests[0].payload.telegram_init_data_keys, ['query_id', 'auth_date', 'hash', 'user']);
});

test('reportTelegramBootstrapDiagnostic marks hash as initData source', async () => {
    const requests = [];

    global.window = createWindow({
        hash: '#tgWebAppData=query_id%3Dhash%26auth_date%3D123%26hash%3Dabcdef123456%26user%3D%257B%2522id%2522%253A778%257D&tgWebAppStartParam=ticket_43',
    });
    global.window.axios.post = async (url, payload) => {
        requests.push({ url, payload });
        return { data: { message: 'ok' } };
    };

    await reportTelegramBootstrapDiagnostic({
        page: '/telegram-app/',
        error: new Error('Откройте приложение через Telegram.'),
        attempts: 8,
        delayMs: 250,
    });

    assert.equal(requests.length, 1);
    assert.equal(requests[0].payload.telegram_init_data_source, 'hash');
    assert.equal(requests[0].payload.telegram_init_data_user_id, '778');
    assert.equal(requests[0].payload.telegram_start_param, 'ticket_43');
});
