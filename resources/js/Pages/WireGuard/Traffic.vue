<script setup>
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

const applyFilters = (event) => {
    const form = new FormData(event.target);
    router.get('/traffic', Object.fromEntries(form.entries()), { preserveState: true, preserveScroll: true });
};

const reset = () => router.get('/traffic');
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
                <select id="server_id" name="server_id" :value="filters.server_id">
                    <option v-for="server in servers" :key="server.id" :value="server.id">{{ server.name }}</option>
                </select>
            </div>

            <div class="field">
                <label>Время сервера (UTC)</label>
                <input :value="server_time" type="datetime-local" readonly>
            </div>

            <div class="field">
                <label for="user_id">Участник</label>
                <select id="user_id" name="user_id" :value="filters.user_id || ''">
                    <option value="">Не выбран</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </div>

            <div class="field">
                <label for="start_date">Начало</label>
                <input id="start_date" name="start_date" type="datetime-local" :value="filters.start_date">
            </div>

            <div class="field">
                <label for="end_date">Конец</label>
                <input id="end_date" name="end_date" type="datetime-local" :value="filters.end_date">
            </div>

            <div class="actions">
                <button class="button" type="submit">Отфильтровать</button>
                <button class="button button--secondary" type="button" @click="reset">Сбросить</button>
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
