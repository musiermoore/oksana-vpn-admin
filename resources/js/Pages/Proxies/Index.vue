<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    proxies: Array,
});

const destroyProxy = (proxy) => {
    const destroyLink = proxy?.links?.destroy;

    if (destroyLink && confirm(`Удалить прокси ${proxy.name}?`)) {
        router.delete(destroyLink);
    }
};
</script>

<template>
    <Head title="Прокси" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Прокси</h1></div>
            <div class="actions">
                <Link class="button" href="/proxies/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Host</th>
                    <th>Port</th>
                    <th>HTTPS</th>
                    <th>Ready</th>
                    <th>Серверов</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="proxy in proxies" :key="proxy.id">
                    <td>{{ proxy.id }}</td>
                    <td>{{ proxy.name }}</td>
                    <td>{{ proxy.host }}</td>
                    <td>{{ proxy.port }}</td>
                    <td>{{ proxy.is_https ? 'Да' : 'Нет' }}</td>
                    <td>{{ proxy.is_ready ? 'Да' : 'Нет' }}</td>
                    <td>{{ proxy.linked_servers_count }}</td>
                    <td>
                        <div class="actions">
                            <Link v-if="proxy.links?.edit" class="button button--secondary" :href="proxy.links.edit">Изменить</Link>
                            <button
                                v-if="proxy.links?.destroy"
                                class="button button--danger"
                                type="button"
                                @click="destroyProxy(proxy)"
                            >
                                Удалить
                            </button>
                            <span v-if="!proxy.links?.edit && !proxy.links?.destroy">—</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
