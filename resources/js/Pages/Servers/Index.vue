<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

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

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Серверы</h1></div>
            <div class="actions">
                <button class="button button--secondary" type="button" @click="openSortModal">Сортировка connect</button>
                <Link class="button button--secondary" href="/xui-debug">3x-ui Debug</Link>
                <Link class="button" href="/servers/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
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
                            <Link v-if="server.links?.edit" class="button button--secondary" :href="server.links.edit">Изменить</Link>
                            <button
                                class="button"
                                :class="server.is_active ? 'button--danger' : 'button--success'"
                                type="button"
                                @click="toggleServer(server)"
                            >
                                {{ server.is_active ? 'Отключить' : 'Включить' }}
                            </button>
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

    <div v-if="isSortModalOpen" class="sort-modal" @click.self="closeSortModal">
        <section class="page-card stack sort-modal__card">
            <div class="page-header">
                <div>
                    <h2>Сортировка connect</h2>
                    <p>Порядок влияет на `/connect`, `/connect-json` и соседние debug-методы.</p>
                </div>
                <div class="actions">
                    <button class="button button--secondary" type="button" @click="closeSortModal">Закрыть</button>
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
                <button class="button" type="button" :disabled="!hasSortChanges" @click="saveSortOrder">Сохранить порядок</button>
                <button class="button button--secondary" type="button" @click="closeSortModal">Отмена</button>
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
