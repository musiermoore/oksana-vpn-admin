<script setup>
import { onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import { ensureTelegramAppSession, getTelegramProfile, normalizeTelegramAppError } from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const telegramProfile = ref(null);

const formatSubscriptionDate = (value) => {
    if (!value) {
        return 'Неактивна';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Неактивна'
        : `Активна до ${date.toLocaleDateString('ru-RU')}`;
};

onMounted(async () => {
    telegramProfile.value = getTelegramProfile();

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
        title="Ваш безопасный доступ"
        description="Управляйте подпиской, пополняйте баланс и обращайтесь в поддержку в пару нажатий."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-card tg-state-card">
            <h2>Подключаем профиль</h2>
            <p>Проверяем вход через Telegram и загружаем ваши данные.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-card tg-state-card">
            <h2>Не удалось открыть mini-app</h2>
            <p>{{ error }}</p>
        </section>

        <template v-else>
            <section class="tg-grid tg-grid--two">
                <article class="tg-card">
                    <span class="tg-card__eyebrow">Профиль</span>
                    <h2>{{ user?.name || telegramProfile?.first_name || 'Пользователь' }}</h2>
                    <p class="tg-muted">Аккаунт привязан к Telegram и готов к использованию.</p>

                    <div class="tg-info-list">
                        <div class="tg-info-row">
                            <span>Telegram</span>
                            <strong>{{ user?.telegram || 'Не указан' }}</strong>
                        </div>
                        <div class="tg-info-row">
                            <span>Баланс</span>
                            <strong>{{ user?.balance ?? 0 }} ₽</strong>
                        </div>
                        <div class="tg-info-row">
                            <span>Подписка</span>
                            <strong>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</strong>
                        </div>
                    </div>
                </article>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
