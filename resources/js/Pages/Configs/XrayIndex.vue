<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    users: Array,
    tabs: Array,
});

const unlinkConfig = (config) => {
    if (confirm(`Отвязать конфиг ${config.name}?`)) {
        router.delete(config.links.destroy);
    }
};

const toggleConfig = (config) => {
    if (!config.supports_toggle) {
        return;
    }

    router.post(config.enable ? config.links.disable : config.links.enable);
};
</script>

<template>
    <Head title="Xray Configs" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Xray Configs</h1>
                <p>Управление VLESS и Shadowsocks-конфигами участников.</p>
            </div>
            <div class="actions">
                <Link class="button" href="/xray-configs/create">Создать</Link>
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
                            <div v-for="config in user.configs" :key="`${config.protocol}-${config.id}`" class="item-row">
                                <Link :href="config.links.edit">
                                    <strong>[{{ config.protocol_label }}]</strong>
                                    {{ config.server.code }}: {{ config.name }}
                                </Link>
                                <div class="actions">
                                    <button
                                        v-if="config.supports_toggle"
                                        class="button"
                                        :class="config.enable ? 'button--danger' : 'button--success'"
                                        type="button"
                                        @click="toggleConfig(config)"
                                    >
                                        {{ config.enable ? 'Отключить' : 'Включить' }}
                                    </button>
                                    <button class="button button--danger" type="button" @click="unlinkConfig(config)">Отвязать</button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
