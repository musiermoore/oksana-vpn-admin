<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    servers: Array,
});

const destroyServer = (server) => {
    const destroyLink = server?.links?.destroy;

    if (destroyLink && confirm(`Удалить сервер ${server.name}?`)) {
        router.delete(destroyLink);
    }
};
</script>

<template>
    <Head title="Серверы" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Серверы</h1></div>
            <div class="actions">
                <Link class="button button--secondary" href="/xui-debug">3x-ui Debug</Link>
                <Link class="button" href="/servers/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Сокращение</th>
                    <th>IP</th>
                    <th>Тип</th>
                    <th>Активен</th>
                    <th>HTTPS</th>
                    <th>Готов</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="server in servers" :key="server.id">
                    <td>{{ server.name }}</td>
                    <td>{{ server.code }}</td>
                    <td>{{ server.ip }}</td>
                    <td>{{ server.type }}</td>
                    <td>{{ server.is_active ? 'Да' : 'Нет' }}</td>
                    <td>{{ server.is_https ? 'Да' : 'Нет' }}</td>
                    <td>{{ server.is_ready ? 'Да' : 'Нет' }}</td>
                    <td>
                        <div class="actions">
                            <Link v-if="server.links?.edit" class="button button--secondary" :href="server.links.edit">Изменить</Link>
                            <button
                                v-if="server.links?.destroy"
                                class="button button--danger"
                                type="button"
                                @click="destroyServer(server)"
                            >
                                Удалить
                            </button>
                            <span v-if="!server.links?.edit && !server.links?.destroy">—</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
