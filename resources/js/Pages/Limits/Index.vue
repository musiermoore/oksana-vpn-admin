<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    configs: Array,
});

const destroyLimit = (limit) => confirm('Удалить ограничение?') && router.delete(limit.links.destroy);
</script>

<template>
    <Head title="Ограничения" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Ограничения</h1></div>
            <div class="actions">
                <a class="button" href="/limits/create">Создать</a>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Участник</th>
                    <th>Сервер</th>
                    <th>Конфиг</th>
                    <th>Адрес</th>
                    <th>Ограничения</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="config in configs" :key="config.id">
                    <td>{{ config.user?.full_name }}</td>
                    <td>{{ config.server?.name }}</td>
                    <td>{{ config.name }}</td>
                    <td>{{ config.address?.replace('/24', '') }}</td>
                    <td>
                        <div class="list">
                            <div v-for="limit in config.limits" :key="limit.id" class="item-row">
                                <div>{{ limit.amount }} Мбит/с</div>
                                <button class="button button--danger" type="button" @click="destroyLimit(limit)">Удалить</button>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
