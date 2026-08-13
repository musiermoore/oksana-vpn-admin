import test from 'node:test';
import assert from 'node:assert/strict';

import {
    getTelegramInitData,
    getTelegramStartParam,
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

const createWindow = ({ initData = '', search = '', hash = '', storedInitData = '' } = {}) => {
    const sessionStorage = new MemoryStorage();

    if (storedInitData !== '') {
        sessionStorage.setItem('telegram-mini-app-last-init-data', storedInitData);
    }

    return {
        Telegram: {
            WebApp: {
                initData,
                initDataUnsafe: {},
            },
        },
        axios: {
            post: async () => ({ data: { message: 'ok' } }),
        },
        navigator: {
            userAgent: 'Telegram-WebApp-Test',
            language: 'ru-RU',
        },
        location: {
            href: `https://example.com/telegram-app/${search}`,
            pathname: '/telegram-app/',
            search,
            hash,
        },
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
