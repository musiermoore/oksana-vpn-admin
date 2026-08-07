<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
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
const referralStatus = ref('');
const referralInput = ref('');
const claimingReferral = ref(false);

const referral = computed(() => user.value?.referral ?? null);
const nextLevelTarget = computed(() => {
    const value = Number(referral.value?.next_level_active_referrals ?? 5);
    return value > 0 ? value : 5;
});
const activeReferrals = computed(() => Number(referral.value?.active_referrals_count ?? 0));
const referralsRemaining = computed(() => Math.max(0, Number(referral.value?.remaining_to_next_level ?? 0)));
const totalDiscountPercent = computed(() => Number(referral.value?.total_discount_percent ?? 0));
const permanentDiscountPercent = computed(() => Number(referral.value?.permanent_discount_percent ?? 0));
const accumulatedDiscountPercent = computed(() => Number(referral.value?.accumulated_discount_percent ?? 0));

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

    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent('Подключайся к OksanaVPN по моей ссылке. По ней можно получить бонус при первом подключении.')}`;

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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть реферальную программу.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Реферальная программа"
        description="Правила, ваша ссылка и текущая скидка по приглашениям."
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
            <h2>Не удалось открыть реферальную программу</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section class="tg-section">
                <div class="tg-page-header__copy">
                    <Link :href="routes?.home" class="tg-link-button">
                        <AppIcon name="chevronLeft" />
                        <span>На главную</span>
                    </Link>
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="gift" />
                        <span>Рефералка</span>
                    </div>
                    <h2>Как это работает</h2>
                    <p>Вы делитесь своей ссылкой, а когда приглашённые люди становятся активными, у вас растёт скидка на подписку.</p>
                </div>
            </section>

            <section class="tg-status-card tg-status-card--success">
                <div class="tg-status-card__top">
                    <div>
                        <div class="tg-status-card__title">Ваша выгода</div>
                        <div class="tg-status-card__meta">
                            <span>Текущая скидка: {{ totalDiscountPercent }}%</span>
                            <span>Постоянная часть: {{ permanentDiscountPercent }}%</span>
                            <span>Накопленная часть: {{ accumulatedDiscountPercent }}%</span>
                        </div>
                    </div>

                    <div class="tg-status-card__icon">
                        <AppIcon name="circleCheck" />
                    </div>
                </div>

                <div class="tg-kv-grid">
                    <div class="tg-kv">
                        <span class="tg-kv__label">Активных приглашений</span>
                        <strong class="tg-kv__value">{{ activeReferrals }}</strong>
                    </div>
                    <div class="tg-kv">
                        <span class="tg-kv__label">До следующего уровня</span>
                        <strong class="tg-kv__value">{{ referralsRemaining }}</strong>
                    </div>
                    <div class="tg-kv">
                        <span class="tg-kv__label">Следующая цель</span>
                        <strong class="tg-kv__value">{{ nextLevelTarget }}</strong>
                    </div>
                </div>
            </section>

            <section class="tg-surface-card tg-stack">
                <div class="tg-section__title">Ваша ссылка</div>
                <div class="tg-section__subtitle">Поделитесь ею с человеком, которого хотите пригласить.</div>

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

                <div class="tg-note">
                    <strong>Правила и условия</strong>
                    <p>Скидка засчитывается по активным приглашениям. Чем больше активных пользователей пришло по вашей ссылке, тем выше ваша персональная скидка.</p>
                </div>

                <p v-if="referralStatus" class="tg-success-text">{{ referralStatus }}</p>
            </section>

            <section class="tg-surface-card tg-stack">
                <div class="tg-section__title">Если вам прислали ссылку</div>
                <div class="tg-section__subtitle">Введите код или ссылку, чтобы привязать реферера к своему аккаунту.</div>

                <div class="tg-field">
                    <label for="referral-input">Код или ссылка</label>
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
                    <span>{{ claimingReferral ? 'Привязываем...' : 'Привязать реферера' }}</span>
                </button>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
