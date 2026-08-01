<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filters: Object,
    server_time: String,
    servers: Array,
    users: Array,
    peers: Array,
});

const form = reactive({
    server_id: props.filters.server_id ?? props.servers[0]?.id ?? '',
    user_id: props.filters.user_id ?? '',
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
});

const serverOptions = props.servers.map((server) => ({
    label: server.name,
    value: server.id,
}));

const userOptions = [
    { label: 'Не выбран', value: '' },
    ...props.users.map((user) => ({
        label: user.full_name,
        value: user.id,
    })),
];

const applyFilters = () => {
    router.get('/traffic', form, { preserveState: true, preserveScroll: true });
};

const reset = () => {
    form.server_id = props.servers[0]?.id ?? '';
    form.user_id = '';
    form.start_date = '';
    form.end_date = '';
    router.get('/traffic');
};
</script>

<template>
    <Head title="Трафик" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Трафик</h1>
                <p>Срез по использованию трафика за выбранный интервал.</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="applyFilters">
            <div class="field">
                <label for="server_id">Сервер</label>
                <AppSelect id="server_id" v-model="form.server_id" :options="serverOptions" />
            </div>

            <div class="field">
                <label>Время сервера (UTC)</label>
                <AppInput :value="server_time" type="datetime-local" readonly />
            </div>

            <div class="field">
                <label for="user_id">Участник</label>
                <AppSelect id="user_id" v-model="form.user_id" :options="userOptions" />
            </div>

            <div class="field">
                <label for="start_date">Начало</label>
                <AppInput id="start_date" v-model="form.start_date" type="datetime-local" />
            </div>

            <div class="field">
                <label for="end_date">Конец</label>
                <AppInput id="end_date" v-model="form.end_date" type="datetime-local" />
            </div>

            <div class="actions">
                <AppButton type="submit">Отфильтровать</AppButton>
                <AppButton variant="secondary" type="button" @click="reset">Сбросить</AppButton>
            </div>
        </form>
    </section>

    <section class="grid grid--cards">
        <article v-for="peer in peers" :key="`${peer.telegram}-${peer.name}`" class="stat-card stack">
            <div>
                <h3>{{ peer.telegram || 'Без имени' }}</h3>
                <p class="muted">Конфиг: {{ peer.name }}</p>
            </div>

            <div v-if="Object.keys(peer.formatted_last_traffic || {}).length" class="stack">
                <div v-for="(amount, type) in peer.formatted_last_traffic" :key="type">
                    {{ type === 'sent' ? 'Отправлено' : 'Получено' }}: {{ amount }}
                </div>
            </div>
            <div v-else class="muted">Нет данных по выбранному интервалу.</div>
        </article>
    </section>
</template>
