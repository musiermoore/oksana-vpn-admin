<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    servers: Array,
});

const toggleServer = (server) => {
    const toggleLink = server?.is_active ? server?.links?.disable : server?.links?.enable;

    if (toggleLink) {
        router.post(toggleLink);
    }
};

const destroyServer = (server) => {
    const destroyLink = server?.links?.destroy;

    if (destroyLink && confirm(`Удалить сервер ${server.name}?`)) {
        router.delete(destroyLink);
    }
};
</script>

<template>
    <Head title="Серверы" />

    <AppPageHeader
        title="Серверы"
        description="Управление узлами сети, их состоянием, порядком выдачи в connect и базовыми параметрами подключения."
        :stats="[
            { label: 'Всего серверов', value: servers.length },
            { label: 'Активные', value: servers.filter((server) => server.is_active).length },
            { label: 'Готовы к работе', value: servers.filter((server) => server.is_ready).length },
        ]"
    >
        <template #actions>
            <AppButton variant="secondary" href="/servers/connect-order">Порядок connect</AppButton>
            <AppButton variant="secondary" href="/xui-debug">3x-ui Debug</AppButton>
            <AppButton href="/servers/create">Добавить сервер</AppButton>
        </template>
    </AppPageHeader>

    <section class="grid grid--cards">
        <article class="stat-card stack">
            <div>
                <h3>Управление доступностью</h3>
                <p class="muted">В таблице ниже можно быстро включать и отключать серверы без перехода в форму редактирования.</p>
            </div>
        </article>

        <article class="stat-card stack">
            <div>
                <h3>Порядок в подписке</h3>
                <p class="muted">Отдельная страница “Порядок connect” позволяет настроить выдачу групп в `/connect` и debug-методах.</p>
            </div>
        </article>

        <article class="stat-card stack">
            <div>
                <h3>Операционная логика</h3>
                <p class="muted">Список теперь работает как рабочая зона сети: сначала контекст, потом действия, потом таблица состояния.</p>
            </div>
        </article>
    </section>

    <section class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Реестр серверов</h2>
                <p>Основная таблица по сетевым узлам: порядок, доступность, тип подключения и быстрые действия.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Порядок</th>
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
                        <td>{{ server.sort_order }}</td>
                        <td>{{ server.name }}</td>
                        <td>{{ server.code }}</td>
                        <td>{{ server.ip }}</td>
                        <td>{{ server.type }}</td>
                        <td>{{ server.is_active ? 'Да' : 'Нет' }}</td>
                        <td>{{ server.is_https ? 'Да' : 'Нет' }}</td>
                        <td>{{ server.is_ready ? 'Да' : 'Нет' }}</td>
                        <td>
                            <div class="actions">
                                <AppButton v-if="server.links?.edit" variant="secondary" :href="server.links.edit">Открыть</AppButton>
                                <AppButton
                                    :variant="server.is_active ? 'danger' : 'success'"
                                    type="button"
                                    @click="toggleServer(server)"
                                >
                                    {{ server.is_active ? 'Отключить' : 'Включить' }}
                                </AppButton>
                                <AppButton
                                    v-if="server.links?.destroy"
                                    variant="danger"
                                    type="button"
                                    @click="destroyServer(server)"
                                >
                                    Удалить
                                </AppButton>
                                <span v-if="!server.links?.edit && !server.links?.destroy">—</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</template>
