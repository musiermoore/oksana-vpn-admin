<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    getTelegramProfile,
    normalizeTelegramAppError,
    redirectFromTelegramStartParam,
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    claim_referral_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const telegramProfile = ref(null);
const referralStatus = ref('');
const referralInput = ref('');
const claimingReferral = ref(false);

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

    if (Number(user.value?.debt ?? 0) > 0 || !user.value?.has_active_access) {
        return 'danger';
    }

    return 'success';
});

const accessTitle = computed(() => {
    if (Number(user.value?.debt ?? 0) > 0) {
        return 'Доступ ограничен';
    }

    if (user.value?.has_active_access) {
        return 'Подписка активна';
    }

    return 'Нужна активация';
});

const accessMeta = computed(() => {
    if (!user.value?.subscription_expires_at) {
        return 'Оформите подписку, чтобы получить доступ к конфигам и ссылкам.';
    }

    return `До ${formatShortDate(user.value.subscription_expires_at)}`;
});

const greetingName = computed(() => telegramProfile.value?.first_name || user.value?.name || 'друг');

const nextStepTitle = computed(() => {
    if (Number(user.value?.debt ?? 0) > 0) {
        return 'Сначала закройте долг';
    }

    if (!user.value?.has_active_access) {
        return 'Сначала оформите подписку';
    }

    return 'Подключитесь за пару шагов';
});

const nextStepDescription = computed(() => {
    if (Number(user.value?.debt ?? 0) > 0) {
        return 'После оплаты доступ к WireGuard и VLESS снова заработает.';
    }

    if (!user.value?.has_active_access) {
        return 'Откройте подписку, выберите тариф и сразу вернитесь к подключению.';
    }

    return 'Откройте подключение, выберите протокол и импортируйте конфиг в приложение.';
});

const primaryActionHref = computed(() => {
    if (!user.value?.has_active_access || Number(user.value?.debt ?? 0) > 0) {
        return props.routes?.payments;
    }

    return props.routes?.wireguard;
});

const primaryActionLabel = computed(() => {
    if (!user.value?.has_active_access || Number(user.value?.debt ?? 0) > 0) {
        return 'Продлить подписку';
    }

    return 'Подключиться сейчас';
});

const quickLinks = computed(() => {
    const items = [
        {
            title: 'WireGuard',
            description: 'Быстрый и стабильный вариант для большинства устройств.',
            href: props.routes?.wireguard,
            icon: 'shield',
            iconClass: 'tg-list-card__icon--success',
        },
        {
            title: 'VLESS',
            description: 'Ссылка для приложений, deep links и QR-код.',
            href: props.routes?.vless,
            icon: 'link',
            iconClass: 'tg-list-card__icon',
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
    ];

    if (user.value?.has_vless_wl_configs && whiteListRoute.value) {
        items.splice(2, 0, {
            title: 'Белый список VLESS',
            description: 'Отдельные WL-ссылки для поддерживаемых клиентов.',
            href: whiteListRoute.value,
            icon: 'lock',
            iconClass: 'tg-list-card__icon--blue',
        });
    }

    return items;
});

const referral = computed(() => user.value?.referral ?? null);
const nextLevelTarget = computed(() => {
    const value = Number(referral.value?.next_level_active_referrals ?? 5);
    return value > 0 ? value : 5;
});
const activeReferrals = computed(() => Number(referral.value?.active_referrals_count ?? 0));
const referralsRemaining = computed(() => Math.max(0, Number(referral.value?.remaining_to_next_level ?? 0)));
const progressPercent = computed(() => {
    if (nextLevelTarget.value <= 0) {
        return 100;
    }

    return Math.max(0, Math.min(100, (activeReferrals.value / nextLevelTarget.value) * 100));
});

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

    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent('Присоединяйся к OksanaVPN по моей ссылке')}`;

    if (window.Telegram?.WebApp?.openTelegramLink) {
        window.Telegram.WebApp.openTelegramLink(shareUrl);
        return;
    }

    window.open(shareUrl, '_blank', 'noopener');
};

const claimReferral = async () => {
    if (!referralInput.value.trim()) {
        referralStatus.value = 'Введите код или ссылку.';
        return;
    }

    claimingReferral.value = true;
    referralStatus.value = '';

    try {
        const response = await window.axios.post(props.claim_referral_url, {
            referral: referralInput.value.trim(),
        }, {
            headers: telegramAppHeaders(),
        });

        user.value = response.data?.user ?? user.value;
        referralInput.value = '';
        referralStatus.value = 'Реферер привязан.';
    } catch (requestError) {
        const message = normalizeTelegramAppError(requestError, 'Не удалось привязать реферера.');
        referralStatus.value = message === 'Не удалось привязать реферера.'
            ? 'Не удалось привязать реферера. Проверьте код или ссылку.'
            : message;
    } finally {
        claimingReferral.value = false;
    }
};

onMounted(async () => {
    telegramProfile.value = getTelegramProfile();

    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
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
        description="Меньше шума, больше пользы. Каждый экран ведёт к следующему шагу."
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
                    <h2>Привет, {{ greetingName }}.</h2>
                    <p>{{ nextStepDescription }}</p>
                </div>

                <div class="tg-status-card" :class="`tg-status-card--${accessTone}`">
                    <div class="tg-status-card__top">
                        <div>
                            <div class="tg-status-card__title">{{ accessTitle }}</div>
                            <div class="tg-status-card__meta">
                                <span>{{ accessMeta }}</span>
                                <span v-if="Number(user?.balance ?? 0) >= 0">Баланс: {{ user?.balance ?? 0 }} ₽</span>
                                <span v-if="Number(user?.debt ?? 0) > 0">Долг: {{ user?.debt ?? 0 }} ₽</span>
                            </div>
                        </div>

                        <div class="tg-status-card__icon">
                            <AppIcon :name="accessTone === 'success' ? 'circleCheck' : 'receipt'" />
                        </div>
                    </div>

                    <div class="tg-actions">
                        <Link :href="primaryActionHref" class="tg-button">
                            <AppIcon :name="user?.has_active_access && Number(user?.debt ?? 0) <= 0 ? 'bolt' : 'receipt'" />
                            <span>{{ primaryActionLabel }}</span>
                        </Link>
                        <Link :href="routes?.help" class="tg-button tg-button--secondary">
                            <AppIcon name="circleQuestion" />
                            <span>Нужна помощь?</span>
                        </Link>
                    </div>
                </div>
            </section>

            <section class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Что сделать дальше?</div>
                        <div class="tg-section__subtitle">{{ nextStepTitle }}</div>
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
                        <div class="tg-section__subtitle">Скидка растёт вместе с активными приглашениями.</div>
                    </div>
                </div>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-kv-grid">
                        <div class="tg-kv">
                            <span class="tg-kv__label">Активных друзей</span>
                            <strong class="tg-kv__value">{{ activeReferrals }}</strong>
                        </div>
                        <div class="tg-kv">
                            <span class="tg-kv__label">До следующего уровня</span>
                            <strong class="tg-kv__value">{{ referralsRemaining }}</strong>
                        </div>
                        <div class="tg-kv">
                            <span class="tg-kv__label">Скидка сейчас</span>
                            <strong class="tg-kv__value">{{ referral?.total_discount_percent ?? 0 }}%</strong>
                        </div>
                    </div>

                    <div class="tg-note">
                        <strong>Прогресс {{ Math.round(progressPercent) }}%</strong>
                        <p class="tg-page-note">Следующая цель: {{ nextLevelTarget }} активных приглашений.</p>
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

                    <div class="tg-form-group">
                        <div class="tg-field">
                            <label for="referral-input">Привязать реферера</label>
                            <input
                                id="referral-input"
                                v-model="referralInput"
                                class="tg-input"
                                type="text"
                                placeholder="Вставьте ссылку или код"
                            >
                        </div>

                        <button class="tg-button" type="button" :disabled="claimingReferral" @click="claimReferral">
                            <AppIcon name="link" />
                            <span>{{ claimingReferral ? 'Привязываем...' : 'Привязать' }}</span>
                        </button>
                    </div>

                    <p v-if="referralStatus" class="tg-success-text">{{ referralStatus }}</p>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
