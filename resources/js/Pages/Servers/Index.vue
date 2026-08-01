<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    servers: Array,
    connect_sort_items: Array,
    sort_connect_groups_url: String,
});

const isSortModalOpen = ref(false);
const dragKey = ref(null);
const sortItems = ref([...(props.connect_sort_items ?? [])]);

const hasSortChanges = computed(() => JSON.stringify(sortItems.value.map((item) => item.key ?? `${item.type}:${item.id}`))
    !== JSON.stringify((props.connect_sort_items ?? []).map((item) => item.key ?? `${item.type}:${item.id}`)));

const openSortModal = () => {
    sortItems.value = [...(props.connect_sort_items ?? [])];
    isSortModalOpen.value = true;
};

const closeSortModal = () => {
    isSortModalOpen.value = false;
    dragKey.value = null;
};

const moveSortItem = (targetKey) => {
    const fromIndex = sortItems.value.findIndex((item) => `${item.type}:${item.id}` === dragKey.value);
    const toIndex = sortItems.value.findIndex((item) => `${item.type}:${item.id}` === targetKey);

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const next = [...sortItems.value];
    const [movedItem] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, movedItem);
    sortItems.value = next;
};

const saveSortOrder = () => {
    router.post(props.sort_connect_groups_url, {
        items: sortItems.value.map((item) => ({
            type: item.type,
            id: item.id,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => closeSortModal(),
    });
};

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
            <AppButton variant="secondary" type="button" @click="openSortModal">Порядок connect</AppButton>
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
                <p class="muted">Кнопка “Порядок connect” открывает сортировку групп и влияет на выдачу в `/connect` и debug-методах.</p>
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

    <div v-if="isSortModalOpen" class="sort-modal" @click.self="closeSortModal">
        <section class="page-card stack sort-modal__card">
            <div class="page-header">
                <div>
                    <h2>Сортировка connect</h2>
                    <p>Порядок влияет на `/connect`, `/connect-json` и соседние debug-методы.</p>
                </div>
                <div class="actions">
                    <AppButton variant="secondary" type="button" @click="closeSortModal">Закрыть</AppButton>
                </div>
            </div>

            <div class="sort-list">
                <div
                    v-for="item in sortItems"
                    :key="`${item.type}:${item.id}`"
                    class="sort-item"
                    draggable="true"
                    @dragstart="dragKey = `${item.type}:${item.id}`"
                    @dragover.prevent
                    @drop="moveSortItem(`${item.type}:${item.id}`)"
                >
                    <div class="sort-item__handle">::</div>
                    <div class="sort-item__body">
                        <strong>{{ item.name }}</strong>
                        <div class="hint">{{ item.label }}<template v-if="item.code"> · {{ item.code }}</template></div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <AppButton type="button" :disabled="!hasSortChanges" @click="saveSortOrder">Сохранить порядок</AppButton>
                <AppButton variant="secondary" type="button" @click="closeSortModal">Отмена</AppButton>
            </div>
        </section>
    </div>
</template>

<style scoped>
.sort-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.42);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    z-index: 30;
}

.sort-modal__card {
    width: min(760px, 100%);
    max-height: calc(100vh - 48px);
    overflow: auto;
}

.sort-list {
    display: grid;
    gap: 12px;
}

.sort-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.92);
    cursor: grab;
}

.sort-item__handle {
    font-weight: 700;
    color: #64748b;
    user-select: none;
}

.sort-item__body {
    display: grid;
    gap: 4px;
}
</style>
