<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    loginTelegramAppAndRedirect,
    normalizeTelegramAppError,
    setTelegramAppTelegramUserId,
    setTelegramAppToken,
} from '../../lib/telegramMiniApp';
import { telegramMiniAppApi, telegramMiniAppEndpoint, telegramMiniAppEndpoints, telegramMiniAppRoutes } from '../../lib/telegramMiniAppApi';

const form = ref({
    login: '',
    password: '',
});
const processing = ref(false);
const processingTelegram = ref(false);
const error = ref('');

const submit = async () => {
    processing.value = true;
    error.value = '';

    try {
        const response = await telegramMiniAppApi.post(telegramMiniAppEndpoints.authPassword, form.value);
        const token = response.data?.token ?? '';

        if (token === '') {
            throw new Error('Не удалось выполнить вход.');
        }

        setTelegramAppToken(token);
        setTelegramAppTelegramUserId(response.data?.user?.telegram_id ?? '');
        window.location.href = telegramMiniAppRoutes.home;
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? requestError?.message ?? 'Не удалось выполнить вход.';
    } finally {
        processing.value = false;
    }
};

const submitTelegram = async () => {
    processingTelegram.value = true;
    error.value = '';

    try {
        await loginTelegramAppAndRedirect({
            authUrl: telegramMiniAppEndpoint(telegramMiniAppEndpoints.authTelegram),
            homeUrl: telegramMiniAppRoutes.home,
        });
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось выполнить вход через Telegram.');
    } finally {
        processingTelegram.value = false;
    }
};
</script>

<template>
    <Head title="Вход" />

    <TelegramMiniAppFrame
        title="OksanaVPN"
        description="Вход в личный кабинет."
        :show-navigation="false"
    >
        <section class="tg-section">
            <div class="tg-page-header__copy">
                <h2>Вход</h2>
                <p>Используйте Telegram или логин и пароль от аккаунта.</p>
            </div>

            <form class="tg-surface-card tg-stack" @submit.prevent="submit">
                <button class="tg-button" type="button" :disabled="processingTelegram || processing" @click="submitTelegram">
                    <AppIcon name="send" />
                    {{ processingTelegram ? 'Проверяем Telegram...' : 'Login with Telegram' }}
                </button>

                <label class="tg-field">
                    <span class="tg-field__label">Логин</span>
                    <input
                        v-model="form.login"
                        class="tg-input"
                        type="text"
                        autocomplete="username"
                        required
                    >
                </label>

                <label class="tg-field">
                    <span class="tg-field__label">Пароль</span>
                    <input
                        v-model="form.password"
                        class="tg-input"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <button class="tg-button" type="submit" :disabled="processing">
                    <AppIcon name="key" />
                    {{ processing ? 'Проверяем...' : 'Войти' }}
                </button>

                <p v-if="error" class="tg-error">{{ error }}</p>
            </form>

            <Link :href="telegramMiniAppRoutes.register" class="tg-button tg-button--secondary">
                Создать аккаунт
            </Link>
        </section>
    </TelegramMiniAppFrame>
</template>
