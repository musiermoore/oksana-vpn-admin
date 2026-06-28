<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
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

const primaryNavItems = [
    {
        key: 'wireguard',
        title: 'WireGuard',
        description: 'Конфиги, QR-код и файл для быстрого подключения.',
        glyph: 'WG',
    },
    {
        key: 'vless',
        title: 'VLESS',
        description: 'Deep links для клиентов, raw-ссылка и QR-код.',
        glyph: 'VL',
    },
    {
        key: 'payments',
        title: 'Подписка',
        description: 'Баланс, срок действия и переход к оплате.',
        glyph: '₽',
    },
    {
        key: 'help',
        title: 'Помощь',
        description: 'Инструкции, клиенты и полезные ссылки.',
        glyph: '?',
    },
];

const referral = computed(() => user.value?.referral ?? null);
const nextLevelTarget = computed(() => {
    const value = Number(referral.value?.next_level_active_referrals ?? 5);

    return value > 0 ? value : 5;
});
const activeReferrals = computed(() => Number(referral.value?.active_referrals_count ?? 0));
const progressValue = computed(() => Math.min(activeReferrals.value, nextLevelTarget.value));
const progressPercent = computed(() => {
    if (nextLevelTarget.value <= 0) {
        return 100;
    }

    return Math.max(0, Math.min(100, (progressValue.value / nextLevelTarget.value) * 100));
});
const referralsRemaining = computed(() => Math.max(0, Number(referral.value?.remaining_to_next_level ?? 0)));
const referralStatusTone = computed(() => {
    const text = referralStatus.value.toLowerCase();

    if (text.includes('не удалось') || text.includes('проверьте')) {
        return 'error';
    }

    return 'success';
});

const formatSubscriptionDate = (value) => {
    if (!value) {
        return 'Подписка не активна';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Подписка не активна'
        : `Подписка активна до ${date.toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        })}`;
};

const retry = () => {
    window.location.reload();
};

const copyReferralLink = async () => {
    const link = user.value?.referral?.referral_link;

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
    const link = user.value?.referral?.referral_link;

    if (!link) {
        referralStatus.value = 'Ссылка пока недоступна.';
        return;
    }

    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent('Присоединяйся к VPN по моей ссылке')}`;

    if (window.Telegram?.WebApp?.openTelegramLink) {
        window.Telegram.WebApp.openTelegramLink(shareUrl);
        return;
    }

    window.open(shareUrl, '_blank', 'noopener');
};

const claimReferral = async () => {
    if (!referralInput.value.trim()) {
        referralStatus.value = 'Введите ссылку или код.';
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
        referralStatus.value = 'Реферер привязан';
    } catch (requestError) {
        const message = normalizeTelegramAppError(requestError, 'Не удалось привязать реферера');
        referralStatus.value = message === 'Не удалось привязать реферера'
            ? 'Не удалось привязать реферера. Проверьте код или ссылку и попробуйте ещё раз.'
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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть приложение.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="OksanaVPN"
        description="Ваш безопасный доступ к интернету"
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-panel tg-referral-card tg-referral-card--loading">
            <div class="tg-referral-head">
                <div>
                    <span class="tg-section-label">Реферальная программа</span>
                    <h2>Реферальная программа</h2>
                    <p>Загружаем реферальную программу…</p>
                </div>
            </div>

            <div class="tg-referral-stats-grid">
                <div class="tg-skeleton-card tg-skeleton-card--compact"></div>
                <div class="tg-skeleton-card tg-skeleton-card--compact"></div>
            </div>

            <div class="tg-skeleton-card tg-skeleton-card--progress"></div>
            <div class="tg-skeleton-card tg-skeleton-card--link"></div>
            <div class="tg-skeleton-card tg-skeleton-card--link"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось загрузить данные</h2>
            <p>{{ error || 'Пожалуйста, попробуйте ещё раз через пару секунд' }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section class="tg-panel tg-home-intro">
                <div class="tg-home-intro__brand">
                    <div class="tg-brand-mark tg-brand-mark--large" aria-hidden="true">
                        <span class="tg-brand-mark__core"></span>
                    </div>

                    <div class="tg-home-intro__copy">
                        <h2>OksanaVPN</h2>
                        <p>Ваш безопасный доступ к интернету</p>
                    </div>
                </div>
            </section>

            <section class="tg-panel">
                <span class="tg-section-label">Главное меню</span>

                <div class="tg-menu-grid">
                    <Link
                        v-for="item in primaryNavItems"
                        :key="item.key"
                        :href="routes?.[item.key]"
                        class="tg-menu-card"
                    >
                        <div class="tg-menu-card__badge" aria-hidden="true">{{ item.glyph }}</div>
                        <div class="tg-menu-card__copy">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.description }}</span>
                        </div>
                    </Link>
                </div>

                <Link :href="routes?.support" class="button button--secondary tg-button-full">
                    Поддержка
                </Link>
            </section>

            <section class="tg-panel">
                <span class="tg-section-label">Статус подключения</span>

                <div class="tg-status-row" :class="user?.has_active_access ? 'tg-status-row--success' : 'tg-status-row--danger'">
                    <div class="tg-status-icon" aria-hidden="true">{{ user?.has_active_access ? '✓' : '!' }}</div>
                    <div class="tg-status-copy">
                        <strong>{{ user?.has_active_access ? 'Ваш доступ активен' : 'Доступ требует продления' }}</strong>
                        <span>{{ user?.has_active_access ? 'Подключение готово к использованию' : 'Откройте подписку, чтобы восстановить доступ' }}</span>
                    </div>
                </div>

                <div class="tg-rows">
                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">✦</div>
                        <div class="tg-row-link__copy">
                            <strong>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</strong>
                            <span>Текущий период доступа</span>
                        </div>
                    </div>

                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">@</div>
                        <div class="tg-row-link__copy">
                            <strong>Профиль</strong>
                            <span>{{ user?.telegram || telegramProfile?.username || user?.name || 'Данные аккаунта' }}</span>
                        </div>
                    </div>

                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">₽</div>
                        <div class="tg-row-link__copy">
                            <strong>Баланс</strong>
                            <span>{{ user?.balance ?? 0 }} ₽</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="tg-panel tg-referral-card" v-if="referral">
                <div class="tg-referral-head">
                    <div>
                        <span class="tg-section-label">Реферальная программа</span>
                        <h2>Реферальная программа</h2>
                        <p>Приглашайте друзей и получайте скидку на подписку.</p>
                    </div>
                </div>

                <div class="tg-referral-stats-grid">
                    <article class="tg-referral-stat">
                        <div class="tg-referral-stat__top">
                            <div class="tg-row-link__icon" aria-hidden="true">∞</div>
                            <span>Ваша скидка</span>
                        </div>
                        <strong>{{ referral.total_discount_percent }}%</strong>
                        <p>Накопительная {{ referral.accumulated_discount_percent }}% · постоянная {{ referral.permanent_discount_percent }}%</p>
                    </article>

                    <article class="tg-referral-stat">
                        <div class="tg-referral-stat__top">
                            <div class="tg-row-link__icon" aria-hidden="true">↗</div>
                            <span>Активные рефералы</span>
                        </div>
                        <strong>{{ referral.active_referrals_count }}</strong>
                        <p>До следующего уровня: {{ referral.next_level_active_referrals ?? 'максимум' }}</p>
                    </article>
                </div>

                <section class="tg-referral-progress">
                    <div class="tg-referral-progress__head">
                        <strong>До следующего уровня</strong>
                        <span>{{ progressValue }} из {{ nextLevelTarget }}</span>
                    </div>
                    <div class="tg-referral-progress__track" aria-hidden="true">
                        <span class="tg-referral-progress__fill" :style="{ width: `${progressPercent}%` }"></span>
                    </div>
                    <p>
                        {{
                            referralsRemaining > 0
                                ? `Пригласите ещё ${referralsRemaining} ${referralsRemaining === 1 ? 'друга' : 'друзей'}, чтобы увеличить скидку.`
                                : 'Следующий уровень уже достигнут. Продолжайте приглашать друзей.'
                        }}
                    </p>
                </section>

                <section class="tg-referral-link-box">
                    <div class="tg-referral-link-box__head">
                        <strong>Ваша ссылка</strong>
                    </div>
                    <div class="tg-referral-link-box__value" :title="referral.referral_link || ''">
                        {{ referral.referral_link || 'Укажите TELEGRAM_BOT_USERNAME, чтобы ссылка появилась.' }}
                    </div>
                    <div class="tg-referral-actions">
                        <button class="button tg-button-full" type="button" @click="copyReferralLink">
                            Скопировать ссылку
                        </button>
                        <button class="button button--secondary tg-button-full" type="button" @click="shareReferralLink">
                            Поделиться
                        </button>
                    </div>
                </section>

                <section v-if="referral.can_claim || referral.has_referrer" class="tg-referral-claim">
                    <div class="tg-referral-claim__head">
                        <strong>Вас уже приглашали?</strong>
                        <p v-if="referral.can_claim">Введите код или ссылку приглашения, чтобы привязать реферера к аккаунту.</p>
                        <p v-else-if="referral.has_referrer">Реферер уже привязан</p>
                    </div>

                    <div v-if="referral.can_claim" class="tg-referral-claim__form">
                        <input
                            v-model="referralInput"
                            class="tg-referral-claim__input"
                            type="text"
                            placeholder="ref_123 или ссылка"
                        >
                        <button
                            class="button"
                            type="button"
                            :disabled="claimingReferral"
                            @click="claimReferral"
                        >
                            {{ claimingReferral ? 'Сохраняем...' : 'Привязать' }}
                        </button>
                    </div>
                </section>

                <p
                    v-if="referralStatus"
                    class="tg-referral-toast"
                    :class="{
                        'is-error': referralStatusTone === 'error',
                        'is-success': referralStatusTone === 'success',
                    }"
                >
                    {{ referralStatus }}
                </p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
