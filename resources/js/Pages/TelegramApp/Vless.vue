<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
const copied = ref('');
const loadingQr = ref(false);
const sendingQrToBot = ref(false);
const qrStatus = ref('');

const preferredLinks = computed(() => ([
    {
        key: 'happ_deep_link',
        title: 'Happ',
        description: 'Открыть подписку напрямую в Happ.',
        url: links.value?.happ_deep_link ?? '',
    },
    {
        key: 'v2raytun_deeplink',
        title: 'V2RayTun',
        description: 'Импортировать подписку в V2RayTun.',
        url: links.value?.v2raytun_deeplink ?? '',
    },
]));

const extraLinks = computed(() => ([
    { key: 'v2rayn_deeplink', title: 'V2RayN', url: links.value?.v2rayn_deeplink ?? '' },
    { key: 'v2rayng_deeplink', title: 'V2RayNG', url: links.value?.v2rayng_deeplink ?? '' },
    { key: 'v2raybox_deeplink', title: 'V2Ray Box', url: links.value?.v2raybox_deeplink ?? '' },
    { key: 'sing_box_deeplink', title: 'Sing-box', url: links.value?.sing_box_deeplink ?? '' },
    { key: 'hiddify_deeplink', title: 'Hiddify', url: links.value?.hiddify_deeplink ?? '' },
]).filter((item) => item.url));

const revokeQrUrl = () => {
    if (qrImageUrl.value) {
        URL.revokeObjectURL(qrImageUrl.value);
        qrImageUrl.value = '';
    }
};

const retry = () => {
    window.location.reload();
};

const copyRawLink = async () => {
    const value = links.value?.link ?? '';

    if (!value) {
        copied.value = 'Ссылка пока недоступна.';
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copied.value = 'Ссылка скопирована.';
    } catch {
        copied.value = 'Не удалось скопировать ссылку.';
    }
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

const openLinkResult = () => {
    actionError.value = '';
    step.value = 'links';
};

const openQrResult = async () => {
    loadingQr.value = true;
    actionError.value = '';
    copied.value = '';
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
        qrStatus.value = response.data?.message ?? 'QR-код отправлен в бот.';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить QR-код в бота.');
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
            debtMessage.value = normalizeTelegramAppError(requestError, 'Доступ к VLESS требует активной подписки.');
            return;
        }

        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть VLESS.');
    }
});

onBeforeUnmount(() => {
    revokeQrUrl();
});
</script>

<template>
    <TelegramMiniAppFrame
        title="VLESS"
        description="Откройте deep links для клиентов, покажите QR-код или скопируйте raw-ссылку."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Проверяем доступ...</h2>
            <p>Загружаем VLESS-данные для подключения.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось открыть VLESS</h2>
            <p>{{ error }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else-if="state === 'debt'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">₽</span>
            </div>
            <h2>VLESS недоступен</h2>
            <p>{{ debtMessage }}</p>
            <div class="tg-stack-actions">
                <Link :href="routes?.payments" class="button tg-button-full">Подписка</Link>
                <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
            </div>
        </section>

        <template v-else>
            <section v-if="step === 'menu'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">VLESS</span>
                <h2>Выберите действие</h2>
                <p>Можно сразу открыть ссылку в клиенте или показать QR-код.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openLinkResult">Link</button>
                    <button class="button tg-button-full" type="button" :disabled="loadingQr" @click="openQrResult">
                        {{ loadingQr ? 'Загружаем...' : 'QR-Code' }}
                    </button>
                    <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
                </div>

                <p v-if="actionError" class="field-error">{{ actionError }}</p>
            </section>

            <section v-else-if="step === 'links'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">Link</span>
                <h2>Подключение к VLESS</h2>
                <p>Выберите клиент или скопируйте raw-ссылку.</p>

                <div class="tg-link-list">
                    <button
                        v-for="item in preferredLinks"
                        :key="item.key"
                        class="tg-row-link tg-row-link--button"
                        type="button"
                        @click="openTelegramExternalLink(item.url)"
                    >
                        <div class="tg-row-link__copy">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.description }}</span>
                        </div>
                        <span class="tg-link-pill">Открыть</span>
                    </button>
                </div>

                <div class="tg-raw-link-box">
                    <strong>Raw-ссылка</strong>
                    <code>{{ links?.link }}</code>
                    <button class="button button--secondary tg-button-full" type="button" @click="copyRawLink">
                        Скопировать raw-ссылку
                    </button>
                    <p v-if="copied" class="tg-muted">{{ copied }}</p>
                </div>

                <section v-if="extraLinks.length > 0" class="tg-extra-links">
                    <strong>Дополнительные клиенты</strong>

                    <div class="tg-chip-row">
                        <button
                            v-for="item in extraLinks"
                            :key="item.key"
                            class="tg-chip-button"
                            type="button"
                            @click="openTelegramExternalLink(item.url)"
                        >
                            {{ item.title }}
                        </button>
                    </div>
                </section>

                <div class="tg-stack-actions">
                    <button class="button button--secondary tg-button-full" type="button" @click="step = 'menu'">
                        Назад
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else class="tg-panel tg-panel-stack">
                <span class="tg-section-label">QR-Code</span>
                <h2>QR для VLESS</h2>
                <p>Отсканируйте код в совместимом VLESS-клиенте.</p>

                <div class="tg-qr-card">
                    <img v-if="qrImageUrl" :src="qrImageUrl" alt="VLESS QR code" class="tg-qr-card__image">
                </div>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" :disabled="sendingQrToBot" @click="sendQrToBot">
                        {{ sendingQrToBot ? 'Отправляем...' : 'Отправить в бота' }}
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="step = 'menu'">
                        Назад
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>

                <p v-if="qrStatus" class="tg-muted">{{ qrStatus }}</p>
                <p v-if="actionError" class="field-error">{{ actionError }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
