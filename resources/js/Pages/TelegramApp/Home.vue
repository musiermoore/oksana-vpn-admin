<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    auth_url: String,
    profile_url: String,
    support_tickets_url: String,
    subscription_packages_url: String,
    payment_url: String,
});

const state = ref('loading');
const error = ref('');
const token = ref('');
const user = ref(null);

const telegramUser = computed(() => {
    return window.Telegram?.WebApp?.initDataUnsafe?.user ?? null;
});

const authHeaders = computed(() => {
    return token.value !== ''
        ? { Authorization: `Bearer ${token.value}` }
        : {};
});

const authenticate = async () => {
    const initData = window.Telegram?.WebApp?.initData ?? '';

    if (initData === '') {
        state.value = 'error';
        error.value = 'Open this page from Telegram mini app.';
        return;
    }

    try {
        const authResponse = await window.axios.post(props.auth_url, {
            init_data: initData,
        });

        token.value = authResponse.data.token;

        const profileResponse = await window.axios.get(props.profile_url, {
            headers: authHeaders.value,
        });

        user.value = profileResponse.data.user;
        state.value = 'ready';
    } catch (authError) {
        state.value = 'error';
        error.value = authError?.response?.data?.message ?? 'Telegram authentication failed.';
    }
};

const buySubscription = async (months) => {
    try {
        const response = await window.axios.post(props.payment_url, {
            month: months,
            return_url: window.location.href,
        }, {
            headers: authHeaders.value,
        });

        const confirmationUrl = response?.data?.confirmation_url;

        if (confirmationUrl) {
            window.location.href = confirmationUrl;
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Payment request failed.';
    }
};

onMounted(async () => {
    if (window.Telegram?.WebApp) {
        window.Telegram.WebApp.ready();
        window.Telegram.WebApp.expand();
    }

    await authenticate();
});
</script>

<template>
    <Head title="Telegram Mini App" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Telegram Mini App</h1>
                <p>Backend is connected and ready for Telegram authorization, payments, and support tickets.</p>
            </div>
        </div>
    </section>

    <section v-if="state === 'loading'" class="page-card stack">
        <p>Connecting to Telegram...</p>
    </section>

    <section v-else-if="state === 'error'" class="page-card stack">
        <h2>Unable to open mini app</h2>
        <p>{{ error }}</p>
    </section>

    <template v-else>
        <section class="page-card stack">
            <h2>Profile</h2>
            <p><strong>Name:</strong> {{ user?.name || telegramUser?.first_name || '—' }}</p>
            <p><strong>Telegram:</strong> {{ user?.telegram || telegramUser?.username || '—' }}</p>
            <p><strong>Balance:</strong> {{ user?.balance ?? 0 }}</p>
        </section>

        <section class="page-card stack">
            <h2>Actions</h2>
            <div class="actions">
                <button class="button" type="button" @click="buySubscription(1)">Pay 1 month</button>
                <a class="button button--secondary" :href="support_tickets_url">Support tickets API</a>
                <a class="button button--secondary" :href="subscription_packages_url">Packages API</a>
            </div>
        </section>
    </template>
</template>
