<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    telegram: String,
    code_requested: Boolean,
    code_expires_in_seconds: Number,
});

const requestCodeForm = useForm({
    telegram: props.telegram ?? '',
});

const step = ref(props.code_requested ? 'verify' : 'request');

const verifyForm = useForm({
    telegram: props.telegram ?? '',
    code: '',
});

watch(
    () => requestCodeForm.telegram,
    (value) => {
        verifyForm.telegram = value;
    },
);

const expiresInMinutes = computed(() => Math.max(1, Math.round((props.code_expires_in_seconds ?? 120) / 60)));

const sendCode = () => requestCodeForm.post('/login/code', {
    onSuccess: () => {
        step.value = 'verify';
    },
});

const login = () => verifyForm.post('/login');
const backToRequest = () => {
    step.value = 'request';
    verifyForm.code = '';
};
</script>

<template>
    <Head title="Вход" />

    <section class="auth-shell">
        <form v-if="step === 'request'" class="page-card stack auth-form" @submit.prevent="sendCode">
            <div class="auth-card__intro stack">
                <h1>Авторизация</h1>
                <p>
                    Мы отправим одноразовый код, который живёт {{ expiresInMinutes }} минуты.
                </p>
            </div>

            <label class="field">
                <span>Telegram Username</span>
                <input v-model="requestCodeForm.telegram" type="text" autocomplete="username" required>
                <small style="color: gray">
                    c @ или без него
                </small>
                <small v-if="requestCodeForm.errors.telegram" class="field-error">
                    {{ requestCodeForm.errors.telegram }}
                </small>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="requestCodeForm.processing">Отправить код</button>
            </div>
        </form>

        <form v-else class="page-card stack auth-form" @submit.prevent="login">
            <div class="auth-card__intro stack">
                <span class="auth-card__eyebrow">VPN Admin</span>
                <h1>Авторизация</h1>
                <p>Код отправлен. Он действует {{ expiresInMinutes }} минуты.</p>
            </div>

            <div class="page-header">
                <div>
                    <p>Введите Telegram username и одноразовый код из сообщения.</p>
                </div>
            </div>

            <label class="field">
                <span>Telegram username</span>
                <input v-model="verifyForm.telegram" type="text" autocomplete="username" required>
                <small v-if="verifyForm.errors.telegram" class="field-error">
                    {{ verifyForm.errors.telegram }}
                </small>
            </label>

            <label class="field">
                <span>Код</span>
                <input
                    v-model="verifyForm.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    required
                >
                <small v-if="verifyForm.errors.code" class="field-error">
                    {{ verifyForm.errors.code }}
                </small>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="verifyForm.processing">Войти</button>
                <button class="button button--ghost" type="button" @click="backToRequest">Изменить username</button>
            </div>
        </form>
    </section>
</template>
