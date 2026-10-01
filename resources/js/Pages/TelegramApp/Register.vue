<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import { setTelegramAppTelegramUserId, setTelegramAppToken } from '../../lib/telegramMiniApp';
import { telegramMiniAppApi, telegramMiniAppEndpoints, telegramMiniAppRoutes } from '../../lib/telegramMiniAppApi';

const urlParams = new URLSearchParams(window.location.search);
const initialReferral = urlParams.get('referral')
    ?? urlParams.get('ref')
    ?? urlParams.get('startapp')
    ?? urlParams.get('start')
    ?? '';

const form = ref({
    name: '',
    login: '',
    password: '',
    password_confirmation: '',
    referral: initialReferral,
});
const processing = ref(false);
const error = ref('');
const fieldErrors = ref({});

const submit = async () => {
    processing.value = true;
    error.value = '';
    fieldErrors.value = {};

    try {
        const response = await telegramMiniAppApi.post(telegramMiniAppEndpoints.authRegister, form.value);
        const token = response.data?.token ?? '';

        if (token === '') {
            throw new Error('Не удалось создать аккаунт.');
        }

        setTelegramAppToken(token);
        setTelegramAppTelegramUserId(response.data?.user?.telegram_id ?? '');
        window.location.href = telegramMiniAppRoutes.home;
    } catch (requestError) {
        fieldErrors.value = requestError?.response?.data?.errors ?? {};
        error.value = requestError?.response?.data?.message ?? requestError?.message ?? 'Не удалось создать аккаунт.';
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Head title="Регистрация" />

    <TelegramMiniAppFrame
        title="OksanaVPN"
        description="Создание публичного аккаунта."
        :show-navigation="false"
    >
        <section class="tg-section">
            <div class="tg-page-header__copy">
                <h2>Регистрация</h2>
                <p>Создайте логин и пароль для доступа к кабинету.</p>
            </div>

            <form class="tg-surface-card tg-stack" @submit.prevent="submit">
                <label class="tg-field">
                    <span class="tg-field__label">Имя</span>
                    <input
                        v-model="form.name"
                        class="tg-input"
                        type="text"
                        autocomplete="name"
                        required
                    >
                    <small v-if="fieldErrors.name?.[0]" class="tg-error">{{ fieldErrors.name[0] }}</small>
                </label>

                <label class="tg-field">
                    <span class="tg-field__label">Логин</span>
                    <input
                        v-model="form.login"
                        class="tg-input"
                        type="text"
                        autocomplete="username"
                        required
                    >
                    <small v-if="fieldErrors.login?.[0]" class="tg-error">{{ fieldErrors.login[0] }}</small>
                </label>

                <label class="tg-field">
                    <span class="tg-field__label">Пароль</span>
                    <input
                        v-model="form.password"
                        class="tg-input"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <small v-if="fieldErrors.password?.[0]" class="tg-error">{{ fieldErrors.password[0] }}</small>
                </label>

                <label class="tg-field">
                    <span class="tg-field__label">Повторите пароль</span>
                    <input
                        v-model="form.password_confirmation"
                        class="tg-input"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </label>

                <label class="tg-field">
                    <span class="tg-field__label">Реферальный код</span>
                    <input
                        v-model="form.referral"
                        class="tg-input"
                        type="text"
                        autocomplete="off"
                        placeholder="ref_123"
                    >
                    <small v-if="fieldErrors.referral?.[0]" class="tg-error">{{ fieldErrors.referral[0] }}</small>
                </label>

                <button class="tg-button" type="submit" :disabled="processing">
                    <AppIcon name="key" />
                    {{ processing ? 'Создаём...' : 'Создать аккаунт' }}
                </button>

                <p v-if="error" class="tg-error">{{ error }}</p>
            </form>

            <Link :href="telegramMiniAppRoutes.login" class="tg-button tg-button--secondary">
                Уже есть аккаунт
            </Link>
        </section>
    </TelegramMiniAppFrame>
</template>
