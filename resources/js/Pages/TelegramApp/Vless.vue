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
let copyToastTimeoutId = null;

const configHubHref = computed(() => telegramMiniAppRoutes.wireguard);

const preferredLinks = computed(() => ([
    {
        key: 'incy_deeplink',
        title: 'Incy',
        description: 'Открыть подписку в Incy.',
        url: links.value?.incy_deeplink ?? '',
    },
    {
        key: 'happ_deep_link',
        title: 'Happ',
        description: 'Открыть подписку сразу в Happ.',
        url: links.value?.happ_deep_link ?? '',
    },
    {
        key: 'v2raytun_deeplink',
        title: 'V2RayTun',
        description: 'Импортировать подписку в V2RayTun.',
        url: links.value?.v2raytun_deeplink ?? '',
    },
]).filter((item) => item.url));

const legacyLink = computed(() => links.value?.legacy_link || '');
const qrOptions = computed(() => [
    ...preferredLinks.value.map((item) => ({
        key: item.key,
        title: `QR-код для ${item.title}`,
        description: `Открыть подписку через ${item.title}.`,
        target: item.key === 'happ_deep_link' ? 'happ' : item.key === 'v2raytun_deeplink' ? 'v2raytun' : 'incy',
    })),
    {
        key: 'legacy',
        title: 'QR-код для старых приложений',
        description: 'Ссылка старого формата для ручного импорта.',
        target: 'legacy',
    },
]);

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
        title="VLESS"
        description="Получите прямую ссылку, быстрое подключение или QR-код."
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
                    <h2>Стандартные</h2>
                </div>

                <button class="tg-list-card tg-list-card--button" type="button" @click="step = 'links'">
                    <div class="tg-list-card__icon">
                        <AppIcon name="bolt" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Добавить конфигурацию в VPN-приложение</div>
                        <div class="tg-list-card__description">Быстрое подключение для Happ, V2RayTun и других клиентов.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <button class="tg-list-card tg-list-card--button" type="button" :disabled="loadingQr" @click="step = 'qr-options'">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="qrcode" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Показать QR-код</div>
                        <div class="tg-list-card__description">Выберите приложение или старую версию ссылки.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>
            </section>

            <section v-else-if="step === 'links'" class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </Link>
                    <h2>Откройте подписку в приложении</h2>
                    <p>Нажмите на нужный клиент. Если приложение не поддерживает импорт по ссылке, скопируйте прямую ссылку.</p>
                </div>

                <button
                    v-for="item in preferredLinks"
                    :key="item.key"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-list-card__icon">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                    </div>
                    <div class="tg-list-card__aside tg-inline-actions">
                        <button
                            class="tg-icon-button tg-icon-button--soft tg-copy-button"
                            type="button"
                            aria-label="Скопировать ссылку"
                            @click.stop="copyText(item.url, 'Откройте ссылку в браузере, а не внутри приложения.')"
                        >
                            <AppIcon name="copy" />
                        </button>
                    </div>
                </button>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__title">Ссылка для старых приложений</div>
                    <div class="tg-code-block">{{ legacyLink || 'Ссылка недоступна' }}</div>
                    <p class="tg-muted-text">Это ссылка старой версии. Для использования новой версии нажимайте кнопки выше.</p>
                    <div class="tg-inline-actions">
                        <button class="tg-button tg-button--secondary" type="button" @click="copyText(legacyLink)">
                            <AppIcon name="copy" />
                            <span>Скопировать</span>
                        </button>
                        <button class="tg-button tg-button--soft" type="button" @click="step = 'qr-options'">
                            <AppIcon name="qrcode" />
                            <span>Показать QR</span>
                        </button>
                    </div>
                </div>

                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>

            <section v-else-if="step === 'qr-options'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="step = 'menu'">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Выберите QR-код</h2>
                    <p>Каждый QR-код открывает подходящий формат ссылки.</p>
                </div>

                <button
                    v-for="item in qrOptions"
                    :key="item.key"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    :disabled="loadingQr"
                    @click="openQrResult(item.target)"
                >
                    <div class="tg-list-card__icon tg-list-card__icon--blue"><AppIcon name="qrcode" /></div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                    </div>
                    <div class="tg-list-card__aside"><AppIcon name="chevronRight" /></div>
                </button>
            </section>

            <section v-else class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </Link>
                    <h2>Импорт по QR-коду</h2>
                    <p>Откройте совместимый клиент и отсканируйте код.</p>
                </div>

                <div class="tg-qr-card">
                    <img v-if="qrImageUrl" :src="qrImageUrl" alt="VLESS QR" class="tg-qr-card__image">
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" :disabled="sendingQrToBot" @click="sendQrToBot">
                        <AppIcon name="send" />
                        <span>{{ sendingQrToBot ? 'Отправляем...' : 'Отправить QR в Telegram' }}</span>
                    </button>
                    <button class="tg-button tg-button--soft" type="button" @click="copyText(legacyLink)">
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
