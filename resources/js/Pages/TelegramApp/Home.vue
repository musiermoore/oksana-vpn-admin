<script setup>
import { onMounted, ref } from 'vue';
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
        referralStatus.value = response.data?.message ?? 'Реферер сохранён.';
    } catch (requestError) {
        referralStatus.value = normalizeTelegramAppError(requestError, 'Не удалось сохранить реферальную ссылку.');
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
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Загружаем данные...</h2>
            <p>Пожалуйста, подождите</p>

            <div class="tg-skeleton-list">
                <div class="tg-skeleton-card"></div>
                <div class="tg-skeleton-card"></div>
                <div class="tg-skeleton-card"></div>
            </div>
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
                <span class="tg-section-label">Статус подключения</span>

                <div class="tg-status-row tg-status-row--success">
                    <div class="tg-status-icon" aria-hidden="true">✓</div>
                    <div class="tg-status-copy">
                        <strong>Ваш доступ активен</strong>
                        <span>Подключение готово к использованию</span>
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

            <section class="tg-panel" v-if="user?.referral">
                <span class="tg-section-label">Реферальная программа</span>

                <div class="tg-rows">
                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">∞</div>
                        <div class="tg-row-link__copy">
                            <strong>{{ user.referral.total_discount_percent }}% скидки</strong>
                            <span>
                                Накопительная {{ user.referral.accumulated_discount_percent }}%,
                                постоянная {{ user.referral.permanent_discount_percent }}%
                            </span>
                        </div>
                    </div>

                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">↗</div>
                        <div class="tg-row-link__copy">
                            <strong>{{ user.referral.active_referrals_count }} активных рефералов</strong>
                            <span>
                                До следующего уровня:
                                {{ user.referral.remaining_to_next_level > 0 ? user.referral.remaining_to_next_level : 'уровень достигнут' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="tg-payment-hint">
                    <strong>Ваша реферальная ссылка</strong>
                    <p>{{ user.referral.referral_link || 'Укажите TELEGRAM_BOT_USERNAME, чтобы показывать ссылку.' }}</p>
                </div>

                <div class="tg-plan-list">
                    <button class="button tg-button-full" type="button" @click="copyReferralLink">
                        Скопировать ссылку
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="shareReferralLink">
                        Поделиться в Telegram
                    </button>
                </div>

                <div v-if="user.referral.can_claim" class="tg-payment-hint">
                    <strong>Уже были приглашены раньше?</strong>
                    <p>Можно один раз вручную ввести `ref_...` или полную ссылку, чтобы сохранить реферера для существующего аккаунта.</p>
                </div>

                <div v-if="user.referral.can_claim" class="field-group">
                    <input
                        v-model="referralInput"
                        class="input"
                        type="text"
                        placeholder="ref_123 или https://t.me/..."
                    >
                    <button
                        class="button tg-button-full"
                        type="button"
                        :disabled="claimingReferral"
                        @click="claimReferral"
                    >
                        {{ claimingReferral ? 'Сохраняем...' : 'Привязать реферера' }}
                    </button>
                </div>

                <p v-if="referralStatus" class="field-error">{{ referralStatus }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
