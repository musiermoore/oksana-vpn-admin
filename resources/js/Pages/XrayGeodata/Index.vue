<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    create_url: String,
    geodata: { type: Array, default: () => [] },
});

const deleteResource = (resource) => {
    if (window.confirm(`Удалить источник геоданных «${resource.name}»?`)) {
        router.delete(resource.links.destroy);
    }
};

const usageCount = (resource) => resource.custom_configs_count
    + resource.external_subscriptions_count
    + resource.client_mappings_count;
</script>

<template>
    <Head title="Геоданные Xray" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Геоданные Xray</h1>
                <p>Источники GeoIP и GeoSite, доступные в JSON-профилях и внешних подписках.</p>
            </div>
            <AppButton :href="props.create_url">Добавить источник</AppButton>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>GeoIP</th>
                    <th>GeoSite</th>
                    <th>Используется</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="resource in props.geodata" :key="resource.id">
                    <td>
                        <strong>{{ resource.name }}</strong>
                        <div v-if="resource.description" class="muted-text">{{ resource.description }}</div>
                    </td>
                    <td class="geodata-url">{{ resource.geoip_url || '—' }}</td>
                    <td class="geodata-url">{{ resource.geosite_url || '—' }}</td>
                    <td>{{ usageCount(resource) }}</td>
                    <td>{{ resource.is_active ? 'Активен' : 'Отключён' }}</td>
                    <td>
                        <div class="actions">
                            <AppButton variant="secondary" :href="resource.links.edit">Изменить</AppButton>
                            <AppButton variant="danger" type="button" @click="deleteResource(resource)">Удалить</AppButton>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="!props.geodata.length" class="empty-state">Источников геоданных пока нет.</div>
    </section>
</template>

<style scoped>
.geodata-url {
    max-width: 28rem;
    overflow-wrap: anywhere;
}

.muted-text {
    margin-top: 0.25rem;
    color: var(--text-muted, #777);
}
</style>
