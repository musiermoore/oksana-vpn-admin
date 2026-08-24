<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    openTelegramExternalLink,
    redirectFromTelegramStartParam,
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';
import { telegramChatLinks } from '../../lib/telegramChatLinks';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    subscription_packages_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const referralStatus = ref('');
const packages = ref([]);

const whiteListRoute = computed(() => {
    if (!props.routes?.vless_wl) {
        return '';
    }

    return `${props.routes.vless_wl}?step=links`;
});

const accessTone = computed(() => {
    if (!user.value) {
        return 'warning';
    }

    if (!user.value?.has_active_access) {
        return 'danger';
    }

    return 'success';
});

const accessTitle = computed(() => {
    if (user.value?.has_active_access) {
        return `Подписка активна до ${formatShortDate(user.value.subscription_expires_at)}`;
    }

    return 'Нужна активация';
});

const accessMeta = computed(() => {
    if (!user.value?.subscription_expires_at) {
        return 'Оформите подписку, чтобы получить доступ к конфигам и ссылкам.';
    }

    return `До ${formatShortDate(user.value.subscription_expires_at)}`;
});

const nextSectionSubtitle = computed(() => {
    if (!user.value?.has_active_access) {
        return 'Сначала оформите подписку, затем откройте конфиги.';
    }

    return 'Выберите нужный раздел.';
});

const primaryActionHref = computed(() => {
    if (!user.value?.has_active_access) {
        return props.routes?.payments;
    }

    return props.routes?.wireguard;
});

const hasTrialPackage = computed(() => packages.value.some((item) => Boolean(item?.is_trial)));

const primaryActionLabel = computed(() => {
    if (!user.value?.subscription_expires_at && hasTrialPackage.value) {
        return 'Пробный период';
    }

    if (!user.value?.has_active_access) {
        return 'Продлить подписку';
    }

    return 'Открыть конфиги';
});

const hasPositiveBalance = computed(() => Number(user.value?.balance ?? 0) > 0);

const quickLinks = computed(() => {
    const items = [
        {
            title: 'Стандартные',
            description: 'Прямая ссылка, быстрое подключение и QR-код.',
            href: props.routes?.vless,
            icon: 'shield',
            iconClass: 'tg-list-card__icon',
        },
        {
            title: 'WireGuard',
            description: 'Конфиги, QR-код и отправка файла в Telegram.',
            href: props.routes?.wireguard,
            icon: 'shield',
            iconClass: 'tg-list-card__icon--success',
        },
        {
            title: 'Подписка и оплата',
            description: 'Статус, баланс, продление и подарочные коды.',
            href: props.routes?.payments,
            icon: 'receipt',
            iconClass: 'tg-list-card__icon--warning',
        },
        {
            title: 'Помощь и приложения',
            description: 'Инструкции, клиенты и подсказки по настройке.',
            href: props.routes?.help,
            icon: 'circleQuestion',
            iconClass: 'tg-list-card__icon--blue',
        },
        {
            title: 'Розыгрыш',
            description: 'Участвуйте явно и увеличивайте шанс за новых рефералов текущей кампании.',
            href: props.routes?.giveaway,
            icon: 'gift',
            iconClass: 'tg-list-card__icon--warning',
        },
    ];

    if (user.value?.has_vless_wl_configs && whiteListRoute.value) {
        items.splice(2, 0, {
            title: 'Белые списки',
            description: 'Отдельные БС-ссылки для поддерживаемых клиентов.',
            href: whiteListRoute.value,
            icon: 'shield',
            iconClass: 'tg-list-card__icon--blue',
        });
    }

    return items;
});

const referral = computed(() => user.value?.referral ?? null);

const formatShortDate = (value) => {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'дата не указана';
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const retry = () => {
    window.location.reload();
};

const loadPackages = async () => {
    const response = await window.axios.get(props.subscription_packages_url, {
        headers: telegramAppHeaders(),
    });

    packages.value = response.data?.data ?? [];
};

const copyReferralLink = async () => {
    const link = referral.value?.referral_link;

    if (!link) {
        referralStatus.value = 'Ссылка пока недоступна.';
        return;
    }

    try {
        await navigator.clipboard.writeText(link);
        referralStatus.value = 'Ссылка скопирована.';
    } catch {
        referralStatus.value = 'Не удалось скопировать ссылку.';
    }
};

const shareReferralLink = () => {
    const link = referral.value?.referral_link;

    if (!link) {
        referralStatus.value = 'Ссылка пока недоступна.';
        return;
    }

    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent('Подключайся к OksanaVPN по моей ссылке. По ней можно получить бонус при первом подключении.')}`;

    if (window.Telegram?.WebApp?.openTelegramLink) {
        window.Telegram.WebApp.openTelegramLink(shareUrl);
        return;
    }

    window.open(shareUrl, '_blank', 'noopener');
};

onMounted(async () => {
    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
        await loadPackages();
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть mini app.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="OksanaVPN"
        description="Быстрый доступ к конфигам, подписке и поддержке."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось загрузить данные</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section class="tg-page-header">
                <div class="tg-page-header__copy">
                    <div class="tg-tag" :class="`tg-tag--${accessTone}`">
                        <AppIcon :name="accessTone === 'success' ? 'circleCheck' : 'circleExclamation'" />
                        <span>{{ accessTitle }}</span>
                    </div>
                </div>
                <div class="tg-status-card__meta">
                    <span v-if="hasPositiveBalance">Баланс: {{ user?.balance ?? 0 }} ₽</span>
                    <span v-if="Number(user?.debt ?? 0) > 0">Долг: {{ user?.debt ?? 0 }} ₽</span>
                </div>
                <div class="tg-actions">
                    <Link :href="primaryActionHref" class="tg-button">
                        <AppIcon :name="user?.has_active_access ? 'bolt' : 'receipt'" />
                        <span>{{ primaryActionLabel }}</span>
                    </Link>
                    <Link :href="routes?.help" class="tg-button tg-button--secondary">
                        <AppIcon name="circleQuestion" />
                        <span>Нужна помощь?</span>
                    </Link>
                </div>
            </section>

            <section class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Сообщество</div>
                        <div class="tg-section__subtitle">Новости и общий чат теперь всегда под рукой прямо на главной.</div>
                    </div>
                </div>

                <div class="tg-inline-actions">
                    <button
                        v-for="item in telegramChatLinks"
                        :key="item.title"
                        class="tg-button tg-button--secondary"
                        type="button"
                        @click="openTelegramExternalLink(item.url)"
                    >
                        <AppIcon name="chat" />
                        <span>{{ item.title }}</span>
                    </button>
                </div>
            </section>

            <section class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Что сделать дальше?</div>
                        <div class="tg-section__subtitle">{{ nextSectionSubtitle }}</div>
                    </div>
                </div>

                <Link
                    v-for="item in quickLinks"
                    :key="item.title"
                    :href="item.href"
                    class="tg-list-card"
                >
                    <div class="tg-list-card__icon" :class="item.iconClass">
                        <AppIcon :name="item.icon" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">{{ item.description }}</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>
            </section>

            <section class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Реферальная программа</div>
                        <div class="tg-section__subtitle">Скопируйте ссылку или откройте правила программы.</div>
                    </div>
                </div>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-note">
                        <strong>Поделитесь ссылкой</strong>
                        <p class="tg-page-note">По вашей ссылке человек сможет подключиться с бонусом при первом старте.</p>
                    </div>

                    <div class="tg-inline-actions">
                        <button class="tg-button tg-button--secondary" type="button" @click="copyReferralLink">
                            <AppIcon name="copy" />
                            <span>Скопировать ссылку</span>
                        </button>
                        <button class="tg-button tg-button--soft" type="button" @click="shareReferralLink">
                            <AppIcon name="send" />
                            <span>Поделиться</span>
                        </button>
                    </div>

                    <Link :href="routes?.referrals" class="tg-button tg-button--secondary">
                        <AppIcon name="gift" />
                        <span>Правила и условия</span>
                    </Link>

                    <p v-if="referralStatus" class="tg-success-text">{{ referralStatus }}</p>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
