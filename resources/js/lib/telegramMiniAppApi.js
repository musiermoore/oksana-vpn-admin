import axios from 'axios';
import { telegramAppHeaders } from './telegramMiniApp';

const trimTrailingSlash = (value) => String(value ?? '').replace(/\/+$/, '');
const trimSlashes = (value) => String(value ?? '').replace(/^\/+|\/+$/g, '');

const resolveBasePath = () => {
    const path = window.location.pathname || '/telegram-app';

    if (path === '/public' || path.startsWith('/public/')) {
        return '/public';
    }

    if (['/login', '/register'].includes(trimTrailingSlash(path))) {
        return '';
    }

    return '/telegram-app';
};

export const telegramMiniAppBasePath = trimTrailingSlash(resolveBasePath());

export const telegramMiniAppApi = axios.create({
    baseURL: telegramMiniAppBasePath === '' ? '/' : `${telegramMiniAppBasePath}/`,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

telegramMiniAppApi.interceptors.request.use((config) => {
    Object.entries(telegramAppHeaders()).forEach(([key, value]) => {
        if (typeof config.headers?.set === 'function') {
            config.headers.set(key, value);
            return;
        }

        config.headers = {
            ...(config.headers ?? {}),
            [key]: value,
        };
    });

    return config;
});

export const telegramMiniAppUrl = (path = '') => {
    const normalizedPath = trimSlashes(path);

    if (telegramMiniAppBasePath === '') {
        return normalizedPath === '' ? '/' : `/${normalizedPath}`;
    }

    return normalizedPath === ''
        ? telegramMiniAppBasePath
        : `${telegramMiniAppBasePath}/${normalizedPath}`;
};

export const telegramMiniAppEndpoint = (path = '') => {
    const normalizedPath = trimSlashes(path);

    return normalizedPath === '' ? telegramMiniAppUrl() : telegramMiniAppUrl(normalizedPath);
};

export const telegramMiniAppRoutes = {
    login: telegramMiniAppUrl('login'),
    register: telegramMiniAppUrl('register'),
    home: telegramMiniAppUrl(),
    wireguard: telegramMiniAppUrl('wireguard'),
    vless: telegramMiniAppUrl('vless'),
    vless_wl: telegramMiniAppUrl('vless-wl'),
    payments: telegramMiniAppUrl('payments'),
    help: telegramMiniAppUrl('help'),
    chats: telegramMiniAppUrl('chats'),
    support: telegramMiniAppUrl('support'),
    giveaway: telegramMiniAppUrl('giveaway'),
    referrals: telegramMiniAppUrl('referrals'),
};

export const telegramMiniAppEndpoints = {
    authTelegram: 'auth/telegram',
    authPassword: 'auth/login',
    authRegister: 'auth/register',
    profile: 'me',
    diagnosticsBootstrap: 'diagnostics/bootstrap',
    subscriptionPackages: 'subscription-packages',
    claimReferral: 'referrals/claim',
    giveaway: 'giveaway/current',
    giveawaySummary: 'giveaway/summary',
    giveawayParticipate: 'giveaway/participate',
    payment: 'payments/subscriptions',
    activateSubscriptionCode: 'payments/subscription-codes/activate',
    wireguardConfigs: 'wireguard/configs',
    vlessLink: 'vless/link',
    vlessQrCode: 'vless/qr-code',
    vlessSendQr: 'vless/send-qr',
    vlessWhiteListLink: 'vless-wl/link',
    vlessWhiteListQrCode: 'vless-wl/qr-code',
    vlessWhiteListSendQr: 'vless-wl/send-qr',
    supportTickets: 'support/tickets',
};
