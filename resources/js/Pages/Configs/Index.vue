<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    users: Array,
    tabs: Array,
});

const removeConfig = (config) => {
    if (confirm(`Удалить конфиг ${config.name}?`)) {
        router.delete(config.links.destroy);
    }
};

const toggleConfig = (config) => {
    router.post(config.is_active ? config.links.disable : config.links.enable);
};
</script>

<template>
    <Head title="Конфиги" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Конфиги</h1>
                <p>WireGuard-конфиги по участникам и серверам.</p>
            </div>
            <div class="actions">
                <Link class="button button--secondary" href="/configs/create-bulk">Массовое создание</Link>
                <Link class="button" href="/configs/create">Создать</Link>
            </div>
        </div>

        <div class="tabs">
            <Link v-for="tab in tabs" :key="tab.href" class="chip" :class="{ 'is-active': tab.active }" :href="tab.href">
                {{ tab.label }}
            </Link>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Участник</th>
                    <th>Конфиги</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users" :key="user.id">
                    <td>
                        <Link v-if="user.is_active" :href="user.edit_url">{{ user.full_name }}</Link>
                        <span v-else>{{ user.full_name }}</span>
                    </td>
                    <td>
                        <div class="list">
                            <div v-for="config in user.configs" :key="config.id" class="item-row">
                                <Link :href="config.links.edit">{{ config.server.code }}: {{ config.name }}</Link>
                                <div class="actions">
                                    <button
                                        class="button"
                                        :class="config.is_active ? 'button--danger' : 'button--success'"
                                        type="button"
                                        @click="toggleConfig(config)"
                                    >
                                        {{ config.is_active ? 'Отключить' : 'Включить' }}
                                    </button>
                                    <button class="button button--danger" type="button" @click="removeConfig(config)">Удалить</button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
