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

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    subscription_packages_url: String,
    payment_url: String,
    activate_subscription_code_url: String,
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
const purchaseMode = ref('PERSONAL');
const activationCode = ref('');
const activationError = ref('');
const activationStatus = ref('');
const activatingCode = ref(false);

const availablePackages = computed(() => (
    purchaseMode.value === 'GIFT'
        ? packages.value.filter((item) => !item.is_trial)
        : packages.value
));

const hasTrialPackage = computed(() => packages.value.some((item) => Boolean(item?.is_trial)));
const shouldPromoteTrial = computed(() => !user.value?.subscription_expires_at && !hasDebt.value && hasTrialPackage.value);

const selectedPackage = computed(() => (
    availablePackages.value.find((item) => item.month === selectedMonth.value)
    ?? availablePackages.value[0]
    ?? null
));

const purchasedCodes = computed(() => user.value?.subscription_codes ?? []);
const balanceAmount = computed(() => Number(user.value?.balance ?? 0));
const debtAmount = computed(() => Number(user.value?.debt ?? 0));
const hasDebt = computed(() => debtAmount.value > 0);
const hasPositiveBalance = computed(() => balanceAmount.value > 0);
const hasMoneyForNextMonth = computed(() => Boolean(user.value?.has_money_for_next_subscription_month));
const totalDiscountPercent = computed(() => Number(user.value?.referral?.total_discount_percent ?? 0));

const formatSubscriptionDate = (value) => {
    if (!value) {
        return 'Подписка не активна';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Подписка не активна';
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const durationText = (item) => {
    if (item?.is_trial && Number(item?.days ?? 0) > 0) {
        return `${item.days} дня пробного доступа`;
    }

    const months = Number(item?.month ?? 0);

    if (months === 1) {
        return '1 месяц';
    }

    if (months >= 2 && months <= 4) {
        return `${months} месяца`;
    }

    return `${months} месяцев`;
};

const paymentBreakdown = (item) => {
    const payableNow = Number(item.payable_now ?? item.price ?? 0);
    const totalPrice = Number(item.price ?? 0);
    const balanceApplied = Number(item.balance_applied ?? 0);

    if (Boolean(item.is_trial)) {
        return 'Бесплатно';
    }

    if (balanceApplied <= 0 || payableNow >= totalPrice) {
        return `К оплате ${payableNow} ₽`;
    }

    return `${payableNow} ₽ сейчас, ${balanceApplied} ₽ спишется с баланса`;
};

const formatPrice = (value) => `${Number(value ?? 0).toLocaleString('ru-RU')} ₽`;

const marketingPrice = (item) => {
    if (Boolean(item?.is_trial)) {
        return 'Бесплатно';
    }

    return formatPrice(item?.payable_now ?? item?.price ?? 0);
};

const originalPrice = (item) => {
    if (Boolean(item?.is_trial)) {
        return '';
    }

    const price = Number(item?.price ?? 0);
    const payableNow = Number(item?.payable_now ?? 0);

    if (price <= 0 || price <= payableNow) {
        return '';
    }

    return formatPrice(price);
};

const savingsText = (item) => {
    const discount = Number(item?.discount_percent ?? 0);

    if (discount <= 0 || Boolean(item?.is_trial)) {
        return '';
    }

    return `Экономия ${discount}%`;
};

const formatCode = (value) => {
    if (!value) {
        return '--------';
    }

    return String(value).replace(/(.{4})/g, '$1 ').trim();
};

const giftCodeStatusText = (item) => (item.status === 'activated' ? 'Активирован' : 'Готов к передаче');

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
};

const chooseDefaultPackage = () => {
    selectedMonth.value = availablePackages.value.find((item) => item.month === 12)?.month
        ?? availablePackages.value[0]?.month
        ?? null;
};

const openPackageSelectFor = async (mode) => {
    packageLoadError.value = '';
    error.value = '';
    purchaseMode.value = mode;

    try {
        await loadPackages();
        chooseDefaultPackage();
        if (mode === 'PERSONAL' && shouldPromoteTrial.value) {
            selectedMonth.value = availablePackages.value.find((item) => Boolean(item.is_trial))?.month
                ?? selectedMonth.value;
        }
        screen.value = 'packages';
    } catch (requestError) {
        packageLoadError.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить тарифы.');
    }
};

const cancelPackageSelect = () => {
    payingMonth.value = null;
    error.value = '';
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
            purchase_type: purchaseMode.value,
        }, {
            headers: telegramAppHeaders(),
        });

        paymentResult.value = response.data ?? null;

        if (response.data?.status === 'activated') {
            await refreshAfterPayment();
            screen.value = 'activated';
            return;
        }

        if (response.data?.status === 'gift_code_created') {
            await refreshAfterPayment();
            screen.value = 'gift-created';
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

const copyText = async (value, successMessage) => {
    try {
        await navigator.clipboard.writeText(value);
        activationError.value = '';
        activationStatus.value = successMessage;
    } catch {
        activationError.value = 'Не удалось скопировать.';
    }
};

const activateCode = async () => {
    if (!activationCode.value.trim()) {
        activationError.value = 'Введите код для активации.';
        activationStatus.value = '';
        return;
    }

    activatingCode.value = true;
    activationError.value = '';
    activationStatus.value = '';

    try {
        const response = await window.axios.post(props.activate_subscription_code_url, {
            code: activationCode.value.trim(),
        }, {
            headers: telegramAppHeaders(),
        });

        paymentResult.value = response.data ?? null;
        activationCode.value = '';
        await refreshAfterPayment();
        screen.value = 'code-activated';
    } catch (requestError) {
        activationError.value = normalizeTelegramAppError(requestError, 'Не удалось активировать код.');
    } finally {
        activatingCode.value = false;
    }
};

onMounted(async () => {
    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

    try {
        await loadProfile();
        await loadPackages();
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
        description="Проверяйте статус, продлевайте доступ и активируйте подарочные коды."
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
            <h2>Не удалось загрузить подписку</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section v-if="screen === 'overview'" class="tg-section">
                <div class="tg-status-card" :class="hasDebt ? 'tg-status-card--danger' : user?.subscription_expires_at ? 'tg-status-card--success' : 'tg-status-card--warning'">
                    <div class="tg-status-card__top">
                        <div>
                            <div class="tg-status-card__title">
                                {{ user?.subscription_expires_at ? `Подписка активна до ${formatSubscriptionDate(user?.subscription_expires_at)}` : 'Подписка не активна' }}
                            </div>
                            <div class="tg-status-card__meta">
                                <span v-if="!user?.subscription_expires_at">Оформите подписку, чтобы получить доступ.</span>
                                <span v-if="hasDebt">Есть долг {{ debtAmount }} ₽</span>
                                <span v-else-if="hasPositiveBalance">Баланс {{ balanceAmount }} ₽</span>
                            </div>
                        </div>

                        <div class="tg-status-card__icon">
                            <AppIcon :name="hasDebt ? 'circleExclamation' : 'circleCheck'" />
                        </div>
                    </div>

                    <div class="tg-kv-grid">
                        <div class="tg-kv">
                            <span class="tg-kv__label">Статус</span>
                            <strong class="tg-kv__value" :class="hasDebt ? 'tg-kv__value--danger' : 'tg-kv__value--success'">
                                {{ hasDebt ? 'Ограничен' : (user?.subscription_expires_at ? 'Активен' : 'Не активен') }}
                            </strong>
                        </div>
                        <div class="tg-kv">
                            <span class="tg-kv__label">Скидка</span>
                            <strong class="tg-kv__value">{{ totalDiscountPercent }}%</strong>
                        </div>
                    </div>
                </div>

                <div v-if="hasDebt || !hasMoneyForNextMonth" class="tg-note" :class="hasDebt ? 'tg-note--danger' : 'tg-note--warning'">
                    <strong v-if="hasDebt">Сначала закройте долг</strong>
                    <strong v-else-if="!hasMoneyForNextMonth">Лучше продлить заранее</strong>
                    <p v-if="hasDebt">Пока долг не погашен, доступ к конфигам и ссылкам может быть ограничен.</p>
                    <p v-else-if="!hasMoneyForNextMonth">На следующий месяц может не хватить средств. Лучше продлить заранее, чтобы не потерять доступ.</p>
                </div>

                <div class="tg-actions">
                    <button class="tg-button" type="button" @click="openPackageSelectFor('PERSONAL')">
                        <AppIcon name="receipt" />
                        <span>{{ shouldPromoteTrial ? 'Пробный период' : 'Продлить подписку' }}</span>
                    </button>
                    <button class="tg-button tg-button--secondary" type="button" @click="openPackageSelectFor('GIFT')">
                        <AppIcon name="gift" />
                        <span>Купить подарочный код</span>
                    </button>
                </div>

                <div v-if="shouldPromoteTrial" class="tg-note tg-note--success">
                    <strong>Сначала попробуйте бесплатно</strong>
                    <p>Для новых пользователей доступен пробный период. Его можно активировать прямо на следующем шаге.</p>
                </div>

                <p v-if="packageLoadError" class="tg-error">{{ packageLoadError }}</p>

                <div class="tg-surface-card tg-stack">
                    <div class="tg-section__title">Активировать код</div>
                    <div class="tg-section__subtitle">Если вам прислали подарочный код, введите его здесь.</div>

                    <div class="tg-field">
                        <label for="activation-code">Код</label>
                        <input
                            id="activation-code"
                            v-model="activationCode"
                            class="tg-input"
                            type="text"
                            placeholder="Например, ABCD EFGH JKLM"
                        >
                    </div>

                    <button class="tg-button" type="button" :disabled="activatingCode" @click="activateCode">
                        <AppIcon name="ticket" />
                        <span>{{ activatingCode ? 'Активируем...' : 'Активировать код' }}</span>
                    </button>

                    <p v-if="activationError" class="tg-error">{{ activationError }}</p>
                    <p v-else-if="activationStatus" class="tg-success-text">{{ activationStatus }}</p>
                </div>

                <section v-if="purchasedCodes.length > 0" class="tg-section">
                    <div class="tg-section__head">
                        <div>
                            <div class="tg-section__title">Мои подарочные коды</div>
                            <div class="tg-section__subtitle">Скопируйте код и отправьте человеку, которому хотите подарить доступ.</div>
                        </div>
                    </div>

                    <div
                        v-for="item in purchasedCodes"
                        :key="item.id"
                        class="tg-code-card tg-stack"
                    >
                        <div class="tg-kv">
                            <span class="tg-kv__label">Код</span>
                            <strong class="tg-kv__value">{{ formatCode(item.code) }}</strong>
                        </div>
                        <div class="tg-kv">
                            <span class="tg-kv__label">Срок</span>
                            <strong class="tg-kv__value">{{ item.months }} мес.</strong>
                        </div>
                        <div class="tg-kv">
                            <span class="tg-kv__label">Статус</span>
                            <strong class="tg-kv__value">{{ giftCodeStatusText(item) }}</strong>
                        </div>
                        <button
                            v-if="item.status !== 'activated'"
                            class="tg-button tg-button--secondary"
                            type="button"
                            @click="copyText(item.code, 'Код скопирован.')"
                        >
                            <AppIcon name="copy" />
                            <span>Скопировать код</span>
                        </button>
                    </div>
                </section>
            </section>

            <section v-else-if="screen === 'packages'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="cancelPackageSelect">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>{{ purchaseMode === 'GIFT' ? 'Выберите срок подарочного кода' : 'Выберите срок подписки' }}</h2>
                    <p>{{ purchaseMode === 'GIFT' ? 'После оплаты получите код, который можно передать другому человеку.' : 'Сумма к оплате уже учитывает доступный баланс и скидки.' }}</p>
                </div>

                <div v-if="availablePackages.length === 0" class="tg-state-card">
                    <div class="tg-state-card__icon">
                        <AppIcon name="receipt" />
                    </div>
                    <h2>Тарифы временно недоступны</h2>
                    <p>Попробуйте ещё раз чуть позже.</p>
                </div>

                <button
                    v-for="item in availablePackages"
                    v-else
                    :key="item.month"
                    class="tg-list-card tg-list-card--button"
                    :class="{ 'tg-list-card--selected': selectedMonth === item.month }"
                    type="button"
                    @click="selectedMonth = item.month"
                >
                    <div class="tg-list-card__icon" :class="item.is_trial ? 'tg-list-card__icon--success' : 'tg-list-card__icon--warning'">
                        <AppIcon :name="item.is_trial ? 'circleCheck' : 'receipt'" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">
                            {{ item.is_trial ? 'Пробный период' : `${durationText(item)} — ${marketingPrice(item)}` }}
                        </div>
                        <div class="tg-list-card__description">{{ paymentBreakdown(item) }}</div>
                        <div v-if="originalPrice(item) || savingsText(item)" class="tg-price-meta">
                            <span v-if="originalPrice(item)" class="tg-price-meta__old">{{ originalPrice(item) }}</span>
                            <span v-if="savingsText(item)" class="tg-tag tg-tag--success">{{ savingsText(item) }}</span>
                        </div>
                    </div>
                    <div class="tg-list-card__aside">
                        <span class="tg-tag tg-tag--primary">{{ item.is_trial ? '0 ₽' : formatPrice(item.payable_now) }}</span>
                    </div>
                </button>

                <div class="tg-actions">
                    <button
                        class="tg-button"
                        type="button"
                        :disabled="!selectedPackage || payingMonth !== null || availablePackages.length === 0"
                        @click="buySubscription"
                    >
                        <AppIcon :name="purchaseMode === 'GIFT' ? 'gift' : 'receipt'" />
                        <span>
                            {{
                                payingMonth
                                    ? 'Создаём оплату...'
                                    : purchaseMode === 'GIFT'
                                        ? 'Получить подарочный код'
                                        : selectedPackage?.is_trial
                                            ? 'Активировать пробный доступ'
                                            : 'Перейти к оплате'
                            }}
                        </span>
                    </button>
                </div>

                <p v-if="error" class="tg-error">{{ error }}</p>
            </section>

            <section v-else-if="screen === 'activated'" class="tg-state-card">
                <div class="tg-state-card__icon">
                    <AppIcon name="circleCheck" />
                </div>
                <h2>Подписка активирована</h2>
                <p>{{ paymentResult?.message || 'Доступ успешно продлён.' }}</p>
                <div class="tg-note tg-note--success">
                    <strong>Новый срок</strong>
                    <p>{{ paymentResult?.formatted_end_date || formatSubscriptionDate(user?.subscription_expires_at) }}</p>
                </div>
                <Link :href="routes?.home" class="tg-button">На главную</Link>
            </section>

            <section v-else-if="screen === 'gift-created'" class="tg-state-card">
                <div class="tg-state-card__icon">
                    <AppIcon name="gift" />
                </div>
                <h2>Подарочный код готов</h2>
                <p>{{ paymentResult?.message || 'Передайте код получателю, чтобы он активировал его в mini app.' }}</p>
                <div class="tg-note">
                    <strong>Код</strong>
                    <p>{{ formatCode(paymentResult?.code) }}</p>
                </div>
                <div class="tg-actions">
                    <button class="tg-button" type="button" @click="copyText(paymentResult?.code, 'Код скопирован.')">
                        <AppIcon name="copy" />
                        <span>Скопировать код</span>
                    </button>
                    <button class="tg-button tg-button--secondary" type="button" @click="screen = 'overview'">
                        <AppIcon name="gift" />
                        <span>К моим кодам</span>
                    </button>
                </div>
                <p v-if="activationError" class="tg-error">{{ activationError }}</p>
                <p v-else-if="activationStatus" class="tg-success-text">{{ activationStatus }}</p>
            </section>

            <section v-else-if="screen === 'code-activated'" class="tg-state-card">
                <div class="tg-state-card__icon">
                    <AppIcon name="circleCheck" />
                </div>
                <h2>Код активирован</h2>
                <p>{{ paymentResult?.message || 'Подписка уже добавлена к вашему аккаунту.' }}</p>
                <div class="tg-note tg-note--success">
                    <strong>Новый срок</strong>
                    <p>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</p>
                </div>
                <Link :href="routes?.home" class="tg-button">На главную</Link>
            </section>

            <section v-else class="tg-state-card">
                <div class="tg-state-card__icon">
                    <AppIcon name="receipt" />
                </div>
                <h2>{{ purchaseMode === 'GIFT' ? 'Оплатите подарочный код' : 'Завершите оплату' }}</h2>
                <p>{{ paymentResult?.message || 'Для активации перейдите на страницу оплаты.' }}</p>
                <div class="tg-note">
                    <strong>К оплате</strong>
                    <p>{{ paymentResult?.deposit_amount ?? selectedPackage?.payable_now ?? 0 }} ₽</p>
                </div>
                <div class="tg-actions">
                    <button class="tg-button" type="button" @click="openTelegramExternalLink(paymentResult?.confirmation_url)">
                        <AppIcon name="arrowUpRight" />
                        <span>Перейти к оплате</span>
                    </button>
                    <Link :href="routes?.home" class="tg-button tg-button--secondary">На главную</Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
