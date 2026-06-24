<script setup>
import { computed, onMounted, ref } from 'vue';
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
    subscription_packages_url: String,
    payment_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const packages = ref([]);
const selectedMonth = ref(null);
const payingMonth = ref(null);

const durationText = (months) => {
    if (months === 1) {
        return '30 дней доступа';
    }

    if (months === 3) {
        return '90 дней доступа';
    }

    if (months === 12) {
        return '365 дней доступа';
    }

    return `${months * 30} дней доступа`;
};

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

const selectedPackage = computed(() => packages.value.find((item) => item.month === selectedMonth.value) ?? packages.value[0] ?? null);

const loadData = async () => {
    user.value = await ensureTelegramAppSession({
        authUrl: props.auth_url,
        profileUrl: props.profile_url,
    });

    const response = await window.axios.get(props.subscription_packages_url, {
        headers: telegramAppHeaders(),
    });

    packages.value = response.data?.data ?? [];
    selectedMonth.value = packages.value.find((item) => item.month === 12)?.month
        ?? packages.value[0]?.month
        ?? null;
};

const buySubscription = async () => {
    if (!selectedPackage.value) {
        error.value = 'Выберите тариф.';
        return;
    }

    payingMonth.value = selectedPackage.value.month;
    error.value = '';

    try {
        const response = await window.axios.post(props.payment_url, {
            month: selectedPackage.value.month,
            return_url: window.location.href,
        }, {
            headers: telegramAppHeaders(),
        });

        if (response.data?.confirmation_url) {
            window.location.href = response.data.confirmation_url;
            return;
        }

        if (response.data?.message) {
            window.alert(response.data.message);
        }
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось перейти к оплате.');
    } finally {
        payingMonth.value = null;
    }
};

const retry = () => {
    window.location.reload();
};

onMounted(async () => {
    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

    try {
        await loadData();
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить тарифы.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Подписка"
        description="Выберите удобный тариф и перейдите к оплате картой или через СБП."
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
            <section class="tg-panel tg-plan-summary">
                <span class="tg-section-label">Текущий план</span>

                <div class="tg-plan-summary__row">
                    <div>
                        <strong>{{ user?.subscription_expires_at ? '12 месяцев' : 'План не выбран' }}</strong>
                        <p>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</p>
                    </div>

                    <span v-if="user?.subscription_expires_at" class="badge badge--success">Активен</span>
                </div>
            </section>

            <section class="tg-panel">
                <span class="tg-section-label">Выберите тариф</span>

                <div class="tg-plan-list">
                    <button
                        v-for="item in packages"
                        :key="item.month"
                        class="tg-plan-option"
                        :class="{ 'is-selected': selectedMonth === item.month }"
                        type="button"
                        @click="selectedMonth = item.month"
                    >
                        <span class="tg-plan-option__radio" aria-hidden="true"></span>

                        <div class="tg-plan-option__copy">
                            <strong>{{ item.month }} {{ item.month === 1 ? 'месяц' : item.month < 5 ? 'месяца' : 'месяцев' }}</strong>
                            <span>{{ durationText(item.month) }}</span>
                        </div>

                        <div class="tg-plan-option__meta">
                            <strong>{{ item.price }} ₽</strong>
                            <span v-if="item.discount_percent > 0" class="tg-discount-badge">-{{ item.discount_percent }}%</span>
                        </div>
                    </button>
                </div>

                <div class="tg-payment-hint">
                    <strong>Перейти к оплате картой / СБП</strong>
                    <p>Чтобы перейти к оплате нажмите на кнопку "Оплатить"</p>
                </div>

                <button
                    class="button tg-button-full"
                    type="button"
                    :disabled="!selectedPackage || payingMonth !== null"
                    @click="buySubscription"
                >
                    {{ payingMonth ? 'Переходим к оплате...' : 'Оплатить' }}
                </button>

                <p v-if="error" class="field-error">{{ error }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
