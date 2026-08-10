import test from 'node:test';
import assert from 'node:assert/strict';

import { getTelegramInitData, requireTelegramInitData, resolveTelegramInitData } from './telegramMiniApp.js';

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

const createWindow = ({ initData = '', search = '', storedInitData = '' } = {}) => {
    const sessionStorage = new MemoryStorage();

    if (storedInitData !== '') {
        sessionStorage.setItem('telegram-mini-app-last-init-data', storedInitData);
    }

    return {
        Telegram: {
            WebApp: {
                initData,
            },
        },
        location: {
            search,
        },
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
