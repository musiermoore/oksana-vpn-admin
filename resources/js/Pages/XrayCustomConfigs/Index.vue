<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    create_page_url: String,
    configs: { type: Array, default: () => [] },
});

const duplicateConfig = (config) => router.post(config.links.duplicate);
</script>

<template>
    <Head title="Конфигурации Xray" />
    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Конфигурации Xray</h1><p>Профили с фильтрацией конфигураций по пользователю.</p></div>
            <AppButton :href="props.create_page_url">Создать конфигурацию</AppButton>
        </div>
        <table>
            <thead><tr><th>Название</th><th>Короткое имя</th><th>Видимость</th><th>DNS</th><th>Геоданные</th><th>Статус</th><th></th></tr></thead>
            <tbody>
                <tr v-for="config in props.configs" :key="config.id">
                    <td>{{ config.name }}</td>
                    <td>{{ config.slug }}</td>
                    <td>{{ config.is_public ? 'Публичная' : `Для пользователей: ${config.user_ids.length}` }}</td>
                    <td>{{ config.dns_settings?.name || '—' }}</td>
                    <td>{{ config.geodata?.name || '—' }}</td>
                    <td>{{ config.is_active ? 'Активна' : 'Отключена' }}</td>
                    <td>
                        <div class="actions">
                            <AppButton variant="secondary" :href="config.links.edit">Редактировать</AppButton>
                            <AppButton variant="secondary" type="button" @click="duplicateConfig(config)">Дублировать</AppButton>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="!props.configs.length" class="empty-state">Конфигураций пока нет.</div>
    </section>
</template>
