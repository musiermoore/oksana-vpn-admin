<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    openTelegramExternalLink,
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
const screen = ref('overview');
const error = ref('');
const user = ref(null);
const packages = ref([]);
const selectedMonth = ref(null);
const payingMonth = ref(null);
const paymentResult = ref(null);
const packageLoadError = ref('');

const selectedPackage = computed(() => (
    packages.value.find((item) => item.month === selectedMonth.value)
    ?? packages.value[0]
    ?? null
));

const balanceAmount = computed(() => Number(user.value?.balance ?? 0));
const debtAmount = computed(() => Number(user.value?.debt ?? 0));
const hasDebt = computed(() => Number(user.value?.debt ?? 0) > 0);
const hasMoneyForNextMonth = computed(() => Boolean(user.value?.has_money_for_next_subscription_month));
const totalDiscountPercent = computed(() => Number(user.value?.referral?.total_discount_percent ?? 0));
const hasReferralDiscount = computed(() => totalDiscountPercent.value > 0);

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

const paymentBreakdown = (item) => {
    const payableNow = Number(item.payable_now ?? item.price ?? 0);
    const totalPrice = Number(item.price ?? 0);
    const balanceApplied = Number(item.balance_applied ?? 0);

    if (balanceApplied <= 0 || payableNow >= totalPrice) {
        return `К оплате ${payableNow} ₽`;
    }

    return `Полная стоимость ${totalPrice} ₽ · с баланса спишется ${balanceApplied} ₽`;
};

const retry = () => {
    window.location.reload();
};

const loadProfile = async () => {
    user.value = await ensureTelegramAppSession({
        authUrl: props.auth_url,
        profileUrl: props.profile_url,
    });
};

const loadPackages = async () => {
    const response = await window.axios.get(props.subscription_packages_url, {
        headers: telegramAppHeaders(),
    });

    packages.value = response.data?.data ?? [];
    selectedMonth.value = packages.value.find((item) => item.month === 12)?.month
        ?? packages.value[0]?.month
        ?? null;
};

const openPackageSelect = async () => {
    packageLoadError.value = '';

    try {
        await loadPackages();
        screen.value = 'packages';
    } catch (requestError) {
        packageLoadError.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить тарифы.');
    }
};

const cancelPackageSelect = () => {
    packageLoadError.value = '';
    payingMonth.value = null;
    screen.value = 'overview';
};

const refreshAfterPayment = async () => {
    await loadProfile();
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

        paymentResult.value = response.data ?? null;

        if (response.data?.status === 'activated') {
            await refreshAfterPayment();
            screen.value = 'activated';
            return;
        }

        if (response.data?.confirmation_url) {
            screen.value = 'payment-link';
            return;
        }

        error.value = 'Не удалось получить ссылку на оплату.';
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось перейти к оплате.');
    } finally {
        payingMonth.value = null;
    }
};

onMounted(async () => {
    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

    try {
        await loadProfile();
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить подписку.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Подписка"
        description="Баланс, срок действия и пошаговая покупка подписки внутри mini-app."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Загружаем данные...</h2>
            <p>Сейчас подтянем ваш баланс и активную подписку.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось загрузить подписку</h2>
            <p>{{ error || 'Пожалуйста, попробуйте ещё раз через пару секунд' }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section v-if="screen === 'overview'" class="tg-panel tg-plan-summary tg-panel-stack">
                <span class="tg-section-label">Подписка</span>

                <div class="tg-plan-summary__status">
                    <div>
                        <strong>{{ user?.subscription_expires_at ? 'Подписка активна' : 'Подписка не активна' }}</strong>
                        <p>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</p>
                    </div>
                </div>

                <div v-if="balanceAmount > 0 || debtAmount > 0" class="tg-rows">
                    <div v-if="balanceAmount > 0" class="tg-row-link tg-row-link--plain">
                        <div class="tg-row-link__icon" aria-hidden="true">₽</div>
                        <div class="tg-row-link__copy">
                            <strong>Баланс</strong>
                            <span>{{ balanceAmount }} ₽</span>
                        </div>
                    </div>

                    <div v-if="debtAmount > 0" class="tg-row-link tg-row-link--plain">
                        <div class="tg-row-link__icon" aria-hidden="true">!</div>
                        <div class="tg-row-link__copy">
                            <strong>Долг</strong>
                            <span>{{ debtAmount }} ₽</span>
                        </div>
                    </div>
                </div>

                <div class="tg-notice-stack">
                    <div v-if="hasDebt" class="tg-payment-hint tg-payment-hint--danger">
                        <strong>На аккаунте есть долг</strong>
                        <p>Пока долг не закрыт, доступ к конфигам и VLESS будет ограничен.</p>
                    </div>

                    <div v-else-if="!hasMoneyForNextMonth" class="tg-payment-hint">
                        <strong>На следующий месяц средств не хватает</strong>
                        <p>Рекомендуем пополнить подписку заранее, чтобы доступ не прерывался.</p>
                    </div>

                    <div v-if="hasReferralDiscount" class="tg-payment-hint">
                        <strong>{{ totalDiscountPercent }}% скидки будет учтено при покупке</strong>
                        <p>
                            Накопительная скидка {{ user.referral.accumulated_discount_percent }}%.
                            После успешной оплаты она сбросится, постоянная {{ user.referral.permanent_discount_percent }}% останется.
                        </p>
                    </div>

                    <div v-else class="tg-payment-hint">
                        <strong>Скидка появится, если пригласить друзей</strong>
                        <p>
                            Участвуйте в реферальной программе, чтобы получать скидку на подписку.
                            <Link :href="routes?.home">Открыть реферальную программу на главной</Link>.
                        </p>
                    </div>
                </div>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openPackageSelect">Купить подписку</button>
                    <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
                </div>

                <p v-if="packageLoadError" class="field-error">{{ packageLoadError }}</p>
            </section>

            <section v-else-if="screen === 'packages'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">Выбор тарифа</span>
                <h2>Выберите срок подписки</h2>

                <div v-if="packages.length === 0" class="tg-empty-panel">
                    <h2>Нет доступных тарифов</h2>
                    <p>Сейчас пакеты временно недоступны. Попробуйте чуть позже.</p>
                </div>

                <div v-else class="tg-plan-list">
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
                            <span>{{ paymentBreakdown(item) }}</span>
                        </div>

                        <div class="tg-plan-option__meta">
                            <strong>{{ item.payable_now }} ₽</strong>
                            <span v-if="item.discount_percent > 0" class="tg-discount-badge">-{{ item.discount_percent }}%</span>
                        </div>
                    </button>
                </div>

                <div class="tg-payment-hint">
                    <strong>Перейти к оплате картой / СБП</strong>
                    <p>Показываем сумму к оплате с учётом уже доступного баланса на аккаунте.</p>
                </div>

                <div class="tg-stack-actions">
                    <button
                        class="button tg-button-full"
                        type="button"
                        :disabled="!selectedPackage || payingMonth !== null || packages.length === 0"
                        @click="buySubscription"
                    >
                        {{ payingMonth ? 'Создаём оплату...' : 'Оплатить' }}
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="cancelPackageSelect">
                        Отменить
                    </button>
                </div>

                <p v-if="error" class="field-error">{{ error }}</p>
            </section>

            <section v-else-if="screen === 'activated'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">Готово</span>
                <h2>Подписка активирована</h2>
                <p>{{ paymentResult?.message || 'Оплата завершена, доступ продлён.' }}</p>

                <div class="tg-inline-callout">
                    <span>Новый срок</span>
                    <strong>{{ paymentResult?.formatted_end_date || formatSubscriptionDate(user?.subscription_expires_at) }}</strong>
                </div>

                <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
            </section>

            <section v-else class="tg-panel tg-panel-stack">
                <span class="tg-section-label">Оплата</span>
                <h2>Нужно завершить оплату</h2>
                <p>{{ paymentResult?.message || 'Для активации подписки перейдите к оплате.' }}</p>

                <div class="tg-inline-callout">
                    <span>К оплате</span>
                    <strong>{{ paymentResult?.deposit_amount ?? selectedPackage?.payable_now ?? 0 }} ₽</strong>
                </div>

                <div class="tg-stack-actions">
                    <button
                        class="button tg-button-full"
                        type="button"
                        @click="openTelegramExternalLink(paymentResult?.confirmation_url)"
                    >
                        Перейти к оплате картой / СБП
                    </button>
                    <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
