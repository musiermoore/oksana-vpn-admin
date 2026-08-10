<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    isTelegramDebtError,
    normalizeTelegramAppError,
    openTelegramExternalLink,
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    vless_wl_link_url: String,
});

const initialStep = 'links';

const state = ref('loading');
const step = ref(initialStep);
const error = ref('');
const debtMessage = ref('');
const user = ref(null);
const links = ref(null);
const copyToast = ref('');
const copiedUrl = ref('');
let copyToastTimeoutId = null;

const preferredLinks = computed(() => ([
    {
        key: 'happ_deep_link',
        title: 'Happ',
        description: 'Открыть БС-подписку сразу в Happ.',
        url: links.value?.happ_deep_link ?? '',
    },
    {
        key: 'v2raytun_deeplink',
        title: 'V2RayTun',
        description: 'Импортировать БС-подписку в V2RayTun.',
        url: links.value?.v2raytun_deeplink ?? '',
    },
    {
        key: 'incy_deeplink',
        title: 'Incy',
        description: 'Открыть БС-подписку в Incy.',
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

const copyText = async (value) => {
    if (!value) {
        copiedUrl.value = '';
        showCopyToast('Ссылка недоступна.');
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copiedUrl.value = value;
        showCopyToast('Успешно скопировано.');
    } catch {
        copiedUrl.value = '';
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

    const response = await window.axios.get(props.vless_wl_link_url, {
        headers: telegramAppHeaders(),
    });

    links.value = response.data ?? null;
    state.value = 'ready';
};

onMounted(async () => {
    try {
        await loadData();
    } catch (requestError) {
        if (isTelegramDebtError(requestError)) {
            state.value = 'debt';
            debtMessage.value = normalizeTelegramAppError(requestError, 'Для БС-доступа нужна активная подписка.');
            return;
        }

        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть белый список VLESS.');
    }
});

onBeforeUnmount(() => {
    if (copyToastTimeoutId) {
        window.clearTimeout(copyToastTimeoutId);
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Белые списки"
        description="Отдельные ссылки для клиентов, которым нужен белый список."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось открыть белый список</h2>
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
            <section class="tg-section">
                <div class="tg-page-header__copy">
                    <Link class="tg-link-button" :href="routes?.wireguard">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </Link>
                    <h2>Выберите приложение</h2>
                    <p>Откройте клиент по ссылке. Если хотите сохранить ссылку вручную, скопируйте её.</p>
                </div>

                <button
                    v-for="item in preferredLinks"
                    :key="item.key"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="arrowUpRight" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                    </div>
                    <div class="tg-inline-actions">
                        <button
                            class="tg-icon-button tg-icon-button--soft tg-copy-button"
                            :class="{ 'is-copied': copiedUrl === item.url }"
                            type="button"
                            :aria-label="copiedUrl === item.url ? 'Успешно скопировано' : 'Скопировать ссылку'"
                            :title="copiedUrl === item.url ? 'Успешно скопировано' : 'Скопировать ссылку'"
                            @click.stop="copyText(item.url)"
                        >
                            <AppIcon :name="copiedUrl === item.url ? 'circleCheck' : 'copy'" />
                        </button>
                    </div>
                </button>

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
                            <div class="tg-list-card__description">Открыть БС-ссылку в приложении.</div>
                        </div>
                        <div class="tg-inline-actions">
                            <button
                                class="tg-icon-button tg-icon-button--soft tg-copy-button"
                                :class="{ 'is-copied': copiedUrl === item.url }"
                                type="button"
                                :aria-label="copiedUrl === item.url ? 'Успешно скопировано' : 'Скопировать ссылку'"
                                :title="copiedUrl === item.url ? 'Успешно скопировано' : 'Скопировать ссылку'"
                                @click.stop="copyText(item.url)"
                            >
                                <AppIcon :name="copiedUrl === item.url ? 'circleCheck' : 'copy'" />
                            </button>
                        </div>
                    </button>
                </div>

                <p v-if="copyToast" class="tg-success-text">{{ copyToast }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
