<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    fetchTelegramBinary,
    isTelegramDebtError,
    normalizeTelegramAppError,
    openTelegramExternalLink,
} from '../../lib/telegramMiniApp';
import { telegramMiniAppApi, telegramMiniAppApiEndpoint, telegramMiniAppEndpoint, telegramMiniAppEndpoints, telegramMiniAppRoutes } from '../../lib/telegramMiniAppApi';

const state = ref('loading');
const step = ref('menu');
const error = ref('');
const debtMessage = ref('');
const actionError = ref('');
const user = ref(null);
const links = ref(null);
const qrImageUrl = ref('');
const copyToast = ref('');
const loadingQr = ref(false);
const sendingQrToBot = ref(false);
const qrStatus = ref('');
const qrTarget = ref('legacy');
const showNewSubscription = ref(false);
const showOldSubscription = ref(false);
let copyToastTimeoutId = null;

const configHubHref = computed(() => telegramMiniAppRoutes.wireguard);

const qrTitle = computed(() => {
    if (qrTarget.value === 'connect-v2') {
        return 'QR-код новой подписки';
    }

    if (qrTarget.value === 'connect-v1') {
        return 'QR-код старой подписки';
    }

    if (qrTarget.value === 'legacy') {
        return 'QR-код старого формата';
    }

    return `QR-код для ${qrTarget.value === 'v2raytun' ? 'V2RayTun' : qrTarget.value === 'happ' ? 'Happ' : 'Incy'}`;
});

const qrDescription = computed(() => {
    if (qrTarget.value === 'connect-v2' || qrTarget.value === 'connect-v1') {
        return 'Отсканируйте код в приложении для импорта подписки.';
    }

    if (qrTarget.value === 'legacy') {
        return 'Для ручного импорта в старые или неподдерживаемые приложения.';
    }

    return `Откройте ${qrTarget.value === 'v2raytun' ? 'V2RayTun' : qrTarget.value === 'happ' ? 'Happ' : 'Incy'} и отсканируйте код.`;
});

const qrCopyLink = computed(() => {
    if (qrTarget.value === 'connect-v2') {
        return connectV2Link.value;
    }

    if (qrTarget.value === 'connect-v1') {
        return connectV1Link.value;
    }

    if (qrTarget.value === 'legacy') {
        return legacyLink.value;
    }

    return preferredLinks.value.find((item) => item.target === qrTarget.value)?.url ?? '';
});

const preferredLinks = computed(() => ([
    {
        key: 'incy_deeplink',
        title: 'Incy',
        description: 'Подключить подписку в Incy или открыть QR-код.',
        url: links.value?.incy_deeplink ?? '',
        target: 'incy',
    },
    {
        key: 'happ_deep_link',
        title: 'Happ',
        description: 'Подключить подписку в Happ или открыть QR-код.',
        url: links.value?.happ_deep_link ?? '',
        target: 'happ',
    },
    {
        key: 'v2raytun_deeplink',
        title: 'V2RayTun',
        description: 'Подключить подписку в V2RayTun или открыть QR-код.',
        url: links.value?.v2raytun_deeplink ?? '',
        target: 'v2raytun',
    },
]).filter((item) => item.url));

const legacyLink = computed(() => links.value?.legacy_link || '');
const connectV2Link = computed(() => links.value?.connect_v2_link || links.value?.link || '');
const connectV1Link = computed(() => links.value?.connect_v1_link || legacyLink.value || '');
const revokeQrUrl = () => {
    if (qrImageUrl.value) {
        URL.revokeObjectURL(qrImageUrl.value);
        qrImageUrl.value = '';
    }
};

const showCopyToast = (message) => {
    copyToast.value = message;

    if (copyToastTimeoutId) {
        window.clearTimeout(copyToastTimeoutId);
    }

    copyToastTimeoutId = window.setTimeout(() => {
        copyToast.value = '';
        copyToastTimeoutId = null;
    }, 2200);
};

const copyText = async (value, successMessage = 'Ссылка скопирована.') => {
    if (!value) {
        showCopyToast('Ссылка пока недоступна.');
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        showCopyToast(successMessage);
    } catch {
        showCopyToast('Не удалось скопировать ссылку.');
    }
};

const retry = () => {
    window.location.reload();
};

const loadData = async () => {
    user.value = await ensureTelegramAppSession({
        authUrl: telegramMiniAppApiEndpoint(telegramMiniAppEndpoints.authTelegram),
        profileUrl: telegramMiniAppApiEndpoint(telegramMiniAppEndpoints.profile),
    });

    const response = await telegramMiniAppApi.get(telegramMiniAppEndpoints.vlessLink);

    links.value = response.data ?? null;
    state.value = 'ready';
};

const openQrResult = async (target = 'legacy') => {
    loadingQr.value = true;
    actionError.value = '';
    qrStatus.value = '';
    qrTarget.value = target;
    revokeQrUrl();

    try {
        const response = await fetchTelegramBinary(
            `${telegramMiniAppEndpoint(telegramMiniAppEndpoints.vlessQrCode)}?target=${encodeURIComponent(target)}`,
        );
        qrImageUrl.value = URL.createObjectURL(response.data);
        step.value = 'qr';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось получить QR-код.');
    } finally {
        loadingQr.value = false;
    }
};

const sendQrToBot = async () => {
    sendingQrToBot.value = true;
    actionError.value = '';
    qrStatus.value = '';

    try {
        const response = await telegramMiniAppApi.post(telegramMiniAppEndpoints.vlessSendQr, {});
        qrStatus.value = response.data?.message ?? 'QR-код отправлен в Telegram.';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить QR-код.');
    } finally {
        sendingQrToBot.value = false;
    }
};

onMounted(async () => {
    try {
        await loadData();
    } catch (requestError) {
        if (isTelegramDebtError(requestError)) {
            state.value = 'debt';
            debtMessage.value = normalizeTelegramAppError(requestError, 'Для доступа к VLESS нужна активная подписка.');
            return;
        }

        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть VLESS.');
    }
});

onBeforeUnmount(() => {
    if (copyToastTimeoutId) {
        window.clearTimeout(copyToastTimeoutId);
    }

    revokeQrUrl();
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Xray / VLESS"
        description="Подключите VPN в Incy, Happ, V2RayTun или другом приложении."
    >
        <div v-if="copyToast" class="tg-toast" role="status">{{ copyToast }}</div>
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось открыть VLESS</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else-if="state === 'debt'" class="tg-state-card tg-state-card--warning">
            <div class="tg-state-card__icon">
                <AppIcon name="receipt" />
            </div>
            <h2>Сначала продлите подписку</h2>
            <p>{{ debtMessage }}</p>
            <div class="tg-actions">
                <Link :href="telegramMiniAppRoutes.payments" class="tg-button">Перейти к подписке</Link>
                <Link :href="telegramMiniAppRoutes.home" class="tg-button tg-button--secondary">На главную</Link>
            </div>
        </section>

        <template v-else>
            <section v-if="step === 'menu'" class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </Link>
                    <h2>Xray / VLESS</h2>
                    <p>Выберите приложение для подключения или QR-код.</p>
                </div>

                <div v-for="item in preferredLinks" :key="item.key" class="tg-list-card">
                    <div class="tg-list-card__icon">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                        <div class="tg-inline-actions tg-inline-actions--icons">
                            <button class="tg-icon-button tg-icon-button--soft" type="button" :aria-label="`Открыть ${item.title}`" :title="`Открыть ${item.title}`" @click="openTelegramExternalLink(item.url)">
                                <AppIcon name="bolt" />
                            </button>
                            <button class="tg-icon-button tg-copy-button" type="button" :aria-label="`Скопировать ссылку ${item.title}`" :title="`Скопировать ссылку ${item.title}`" @click="copyText(item.url)">
                                <AppIcon name="copy" />
                            </button>
                            <button class="tg-icon-button" type="button" :aria-label="`Показать QR-код ${item.title}`" :title="`Показать QR-код ${item.title}`" :disabled="loadingQr" @click="openQrResult(item.target)">
                                <AppIcon name="qrcode" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__head">
                        <div>
                            <div class="tg-section__title">Новая подписка</div>
                            <p class="tg-muted-text">Основная ссылка для подключения</p>
                        </div>
                        <button class="tg-icon-button" type="button" :aria-label="showNewSubscription ? 'Скрыть новую подписку' : 'Показать новую подписку'" :title="showNewSubscription ? 'Скрыть ссылку' : 'Показать ссылку'" :aria-expanded="showNewSubscription" @click="showNewSubscription = !showNewSubscription">
                            <AppIcon :name="showNewSubscription ? 'chevronDown' : 'chevronRight'" />
                        </button>
                    </div>
                    <div v-if="showNewSubscription" class="tg-stack">
                        <div class="tg-code-row">
                            <div class="tg-code-row__body">
                                <strong>Новая подписка</strong>
                                <div class="tg-code-block">{{ connectV2Link || 'Ссылка недоступна' }}</div>
                            </div>
                            <button class="tg-icon-button tg-copy-button" type="button" aria-label="Скопировать новую подписку" title="Скопировать новую подписку" @click="copyText(connectV2Link)">
                                <AppIcon name="copy" />
                            </button>
                            <button class="tg-icon-button tg-icon-button--soft" type="button" aria-label="Показать QR-код новой подписки" title="Показать QR-код новой подписки" :disabled="loadingQr" @click="openQrResult('connect-v2')">
                                <AppIcon name="qrcode" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__head">
                        <div>
                            <div class="tg-section__title">Старая подписка</div>
                            <p class="tg-muted-text">Ссылка для старого приложения</p>
                        </div>
                        <button class="tg-icon-button" type="button" :aria-label="showOldSubscription ? 'Скрыть ссылку старой подписки' : 'Показать ссылку старой подписки'" :title="showOldSubscription ? 'Скрыть ссылку' : 'Показать ссылку'" :aria-expanded="showOldSubscription" @click="showOldSubscription = !showOldSubscription">
                            <AppIcon :name="showOldSubscription ? 'chevronDown' : 'chevronRight'" />
                        </button>
                    </div>
                    <div v-if="showOldSubscription" class="tg-stack">
                        <div class="tg-code-row">
                            <div class="tg-code-row__body">
                                <strong>Старая подписка</strong>
                                <div class="tg-code-block">{{ connectV1Link || 'Ссылка недоступна' }}</div>
                            </div>
                            <button class="tg-icon-button tg-copy-button" type="button" aria-label="Скопировать старую подписку" title="Скопировать старую подписку" @click="copyText(connectV1Link)">
                                <AppIcon name="copy" />
                            </button>
                            <button class="tg-icon-button tg-icon-button--soft" type="button" aria-label="Показать QR-код старой подписки" title="Показать QR-код старой подписки" :disabled="loadingQr" @click="openQrResult('connect-v1')">
                                <AppIcon name="qrcode" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__title">QR-код старого формата</div>
                    <p class="tg-muted-text">Для ручного импорта в старые или неподдерживаемые приложения.</p>
                    <div class="tg-inline-actions tg-inline-actions--icons">
                        <button class="tg-icon-button tg-copy-button" type="button" aria-label="Скопировать ссылку старого формата" title="Скопировать ссылку старого формата" @click="copyText(legacyLink)">
                            <AppIcon name="copy" />
                        </button>
                        <button class="tg-icon-button tg-icon-button--soft" type="button" aria-label="Показать QR-код старого формата" title="Показать QR-код старого формата" :disabled="loadingQr" @click="openQrResult('legacy')">
                            <AppIcon name="qrcode" />
                        </button>
                    </div>
                </div>
            </section>

            <section v-else class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </Link>
                    <h2>{{ qrTitle }}</h2>
                    <p>{{ qrDescription }}</p>
                </div>

                <div class="tg-qr-card">
                    <img v-if="qrImageUrl" :src="qrImageUrl" :alt="qrTitle" class="tg-qr-card__image">
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" :disabled="sendingQrToBot" @click="sendQrToBot">
                        <AppIcon name="send" />
                        <span>{{ sendingQrToBot ? 'Отправляем...' : 'Отправить QR в Telegram' }}</span>
                    </button>
                    <button class="tg-button tg-button--soft" type="button" @click="copyText(qrCopyLink)">
                        <AppIcon name="copy" />
                        <span>Скопировать ссылку</span>
                    </button>
                </div>

                <p v-if="qrStatus" class="tg-success-text">{{ qrStatus }}</p>
                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
