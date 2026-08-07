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
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    vless_link_url: String,
    vless_qr_url: String,
    vless_send_qr_url: String,
});

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

const hasWhiteListRoute = computed(() => Boolean(user.value?.has_vless_wl_configs && props.routes?.vless_wl));
const whiteListLinkHref = computed(() => (props.routes?.vless_wl ? `${props.routes.vless_wl}?step=links` : ''));
const configHubHref = computed(() => props.routes?.wireguard || '/telegram-app/wireguard');

const preferredLinks = computed(() => ([
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
    {
        key: 'incy_deeplink',
        title: 'Incy',
        description: 'Открыть подписку в Incy.',
        url: links.value?.incy_deeplink ?? '',
    },
]).filter((item) => item.url));

const extraLinks = computed(() => ([
    { key: 'v2rayn_deeplink', title: 'V2RayN', url: links.value?.v2rayn_deeplink ?? '' },
    { key: 'v2rayng_deeplink', title: 'V2RayNG', url: links.value?.v2rayng_deeplink ?? '' },
    { key: 'v2raybox_deeplink', title: 'V2Ray Box', url: links.value?.v2raybox_deeplink ?? '' },
    { key: 'sing_box_deeplink', title: 'Sing-box', url: links.value?.sing_box_deeplink ?? '' },
    { key: 'hiddify_deeplink', title: 'Hiddify', url: links.value?.hiddify_deeplink ?? '' },
]).filter((item) => item.url));

const rawLink = computed(() => links.value?.raw_link || links.value?.link || '');

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
        authUrl: props.auth_url,
        profileUrl: props.profile_url,
    });

    const response = await window.axios.get(props.vless_link_url, {
        headers: telegramAppHeaders(),
    });

    links.value = response.data ?? null;
    state.value = 'ready';
};

const openQrResult = async () => {
    loadingQr.value = true;
    actionError.value = '';
    qrStatus.value = '';
    revokeQrUrl();

    try {
        const response = await fetchTelegramBinary(props.vless_qr_url);
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
        const response = await window.axios.post(props.vless_send_qr_url, {}, {
            headers: telegramAppHeaders(),
        });
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
        description="Получите ссылку для приложения, скопируйте raw link или покажите QR-код."
        :routes="routes"
        :user="user"
    >
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
                <Link :href="routes?.payments" class="tg-button">Перейти к подписке</Link>
                <Link :href="routes?.home" class="tg-button tg-button--secondary">На главную</Link>
            </div>
        </section>

        <template v-else>
            <section v-if="step === 'menu'" class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Все конфиги</span>
                    </Link>
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="link" />
                        <span>VLESS</span>
                    </div>
                    <h2>Как хотите подключиться?</h2>
                    <p>Обычно удобнее открыть ссылку сразу в VPN-приложении. Если это не подходит, используйте QR-код.</p>
                </div>

                <button class="tg-list-card tg-list-card--button" type="button" @click="step = 'links'">
                    <div class="tg-list-card__icon">
                        <AppIcon name="bolt" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Добавить в VPN-приложение</div>
                        <div class="tg-list-card__description">Deep links для Happ, V2RayTun и других клиентов.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <button class="tg-list-card tg-list-card--button" type="button" :disabled="loadingQr" @click="openQrResult">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="qrcode" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ loadingQr ? 'Готовим QR-код...' : 'Показать QR-код' }}</div>
                        <div class="tg-list-card__description">Подходит, если приложение умеет импортировать по скану.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <Link v-if="hasWhiteListRoute" :href="whiteListLinkHref" class="tg-list-card">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="lock" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">VLESS (Белые списки)</div>
                        <div class="tg-list-card__description">Отдельные ссылки с белыми списками для поддерживаемых клиентов.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>
            </section>

            <section v-else-if="step === 'links'" class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Все конфиги</span>
                    </Link>
                    <h2>Откройте подписку в приложении</h2>
                    <p>Нажмите на нужный клиент. Если приложение не поддерживает импорт по ссылке, скопируйте raw link.</p>
                </div>

                <button
                    v-for="item in preferredLinks"
                    :key="item.key"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-list-card__icon">
                        <AppIcon name="arrowUpRight" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="arrowUpRight" />
                    </div>
                </button>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__title">Ваша ссылка</div>
                    <div class="tg-code-block">{{ rawLink || 'Ссылка недоступна' }}</div>
                    <div class="tg-inline-actions">
                        <button class="tg-button tg-button--secondary" type="button" @click="copyText(rawLink)">
                            <AppIcon name="copy" />
                            <span>Скопировать</span>
                        </button>
                        <button class="tg-button tg-button--soft" type="button" @click="openQrResult">
                            <AppIcon name="qrcode" />
                            <span>Показать QR</span>
                        </button>
                    </div>
                </div>

                <div v-if="extraLinks.length > 0" class="tg-surface-card tg-stack">
                    <div class="tg-section__title">Другие приложения</div>
                    <button
                        v-for="item in extraLinks"
                        :key="item.key"
                        class="tg-list-card tg-list-card--button tg-list-card--soft"
                        type="button"
                        @click="openTelegramExternalLink(item.url)"
                    >
                        <div class="tg-list-card__body">
                            <div class="tg-list-card__title">{{ item.title }}</div>
                            <div class="tg-list-card__description">Открыть подписку в этом приложении.</div>
                        </div>
                        <div class="tg-list-card__aside">
                            <AppIcon name="arrowUpRight" />
                        </div>
                    </button>
                </div>

                <p v-if="copyToast" class="tg-success-text">{{ copyToast }}</p>
                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>

            <section v-else class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="configHubHref">
                        <AppIcon name="chevronLeft" />
                        <span>Все конфиги</span>
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
                    <button class="tg-button tg-button--soft" type="button" @click="copyText(rawLink, 'Ссылка скопирована.')">
                        <AppIcon name="copy" />
                        <span>Скопировать ссылку</span>
                    </button>
                </div>

                <p v-if="qrStatus" class="tg-success-text">{{ qrStatus }}</p>
                <p v-if="copyToast" class="tg-success-text">{{ copyToast }}</p>
                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
