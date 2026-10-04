<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    create_page_url: String,
    configs: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Xray Custom Configs" />
    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Xray Custom Configs</h1><p>User-filtered JSON configuration profiles.</p></div>
            <AppButton :href="props.create_page_url">Создать конфигурацию</AppButton>
        </div>
        <table>
            <thead><tr><th>Name</th><th>Slug</th><th>DNS</th><th>Geodata</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="config in props.configs" :key="config.id">
                    <td>{{ config.name }}</td>
                    <td>{{ config.slug }}</td>
                    <td>{{ config.dns_settings?.name || '—' }}</td>
                    <td>{{ config.geodata?.name || '—' }}</td>
                    <td>{{ config.is_active ? 'Active' : 'Disabled' }}</td>
                    <td><AppButton variant="secondary" :href="config.links.edit">Изменить</AppButton></td>
                </tr>
            </tbody>
        </table>
        <div v-if="!props.configs.length" class="empty-state">Конфигураций пока нет.</div>
    </section>
</template>
