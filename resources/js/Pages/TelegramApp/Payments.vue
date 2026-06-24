<script setup>
import { onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import { ensureTelegramAppSession, normalizeTelegramAppError, telegramAppHeaders } from '../../lib/telegramMiniApp';

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
const payingMonth = ref(null);

const loadData = async () => {
    user.value = await ensureTelegramAppSession({
        authUrl: props.auth_url,
        profileUrl: props.profile_url,
    });

    const response = await window.axios.get(props.subscription_packages_url, {
        headers: telegramAppHeaders(),
    });

    packages.value = response.data?.data ?? [];
};

const buySubscription = async (months) => {
    payingMonth.value = months;
    error.value = '';

    try {
        const response = await window.axios.post(props.payment_url, {
            month: months,
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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось создать оплату.');
    } finally {
        payingMonth.value = null;
    }
};

onMounted(async () => {
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
        title="Оплата подписки"
        description="Выберите удобный срок и перейдите к безопасной оплате через YooKassa."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-card tg-state-card">
            <h2>Загружаем тарифы</h2>
            <p>Считаем стоимость подписки с учётом вашего текущего баланса.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-card tg-state-card">
            <h2>Не удалось открыть оплату</h2>
            <p>{{ error }}</p>
        </section>

        <template v-else>
            <section class="tg-grid tg-grid--cards">
                <article v-for="item in packages" :key="item.month" class="tg-card tg-plan-card">
                    <span class="tg-card__eyebrow">Тариф</span>
                    <h2>{{ item.month }} мес.</h2>
                    <p class="tg-plan-card__price">{{ item.price }} ₽</p>
                    <p class="tg-muted">
                        {{ item.discount_percent > 0 ? `Скидка ${item.discount_percent}%` : 'Базовая стоимость' }}
                    </p>

                    <button
                        class="button tg-button-full"
                        type="button"
                        :disabled="payingMonth === item.month"
                        @click="buySubscription(item.month)"
                    >
                        {{ payingMonth === item.month ? 'Переходим к оплате...' : 'Оплатить' }}
                    </button>
                </article>
            </section>

            <section v-if="error" class="tg-card tg-state-card">
                <p>{{ error }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
