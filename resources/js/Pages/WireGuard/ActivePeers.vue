<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filters: Object,
    servers: Array,
    peerGroups: Array,
});

const updateServer = (event) => {
    router.get('/', { server_id: event.target.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Активные подключения" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Активные подключения</h1>
                <p>Живой обзор по WireGuard-пирам с разбиением на активных и оффлайн.</p>
            </div>
        </div>

        <div class="field">
            <label for="server_id">Сервер</label>
            <select id="server_id" :value="filters.server_id" @change="updateServer">
                <option v-for="server in servers" :key="server.id" :value="server.id">
                    {{ server.name }}
                </option>
            </select>
        </div>
    </section>

    <section v-for="group in peerGroups" :key="group.key" class="stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">{{ group.label }} ({{ group.items.length }})</h2>
            </div>
        </div>

        <div class="grid grid--cards">
            <article v-for="peer in group.items" :key="`${group.key}-${peer.telegram}-${peer.latest_handshake}`" class="stat-card stack">
                <div>
                    <h3>{{ peer.telegram || 'Без имени' }}</h3>
                </div>
                <div class="muted">Последняя активность: {{ peer.latest_handshake }}</div>
                <div>Трафика использовано: {{ peer.transfer }}</div>
                <div v-if="Object.keys(peer.formatted_last_traffic || {}).length" class="stack">
                    <strong>За 10 минут</strong>
                    <div v-for="(amount, type) in peer.formatted_last_traffic" :key="type">
                        {{ type === 'sent' ? 'Отправлено' : 'Получено' }}: {{ amount }}
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
