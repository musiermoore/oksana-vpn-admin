<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    connect_sort_items: Array,
    sort_connect_groups_url: String,
    servers_page_url: String,
});

const sortItems = ref([...(props.connect_sort_items ?? [])]);
const dragKey = ref(null);

const originalKeys = computed(() => (props.connect_sort_items ?? []).map((item) => `${item.type}:${item.id}`));
const currentKeys = computed(() => sortItems.value.map((item) => `${item.type}:${item.id}`));
const hasSortChanges = computed(() => JSON.stringify(currentKeys.value) !== JSON.stringify(originalKeys.value));

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

const moveSortItemByOffset = (index, offset) => {
    const targetIndex = index + offset;

    if (targetIndex < 0 || targetIndex >= sortItems.value.length) {
        return;
    }

    const next = [...sortItems.value];
    const [movedItem] = next.splice(index, 1);
    next.splice(targetIndex, 0, movedItem);
    sortItems.value = next;
};

const saveSortOrder = () => router.post(props.sort_connect_groups_url, {
    items: sortItems.value.map((item, index) => ({
        type: item.type,
        id: item.id,
        sort_order: index,
    })),
}, {
    preserveScroll: true,
});
</script>

<template>
    <Head title="Порядок connect" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Порядок connect</h1>
                <p>Настройте общий порядок серверов, внешних подписок и Xray custom configs в подписке.</p>
            </div>
            <div class="actions">
                <AppButton variant="secondary" :href="servers_page_url">Вернуться к серверам</AppButton>
                <AppButton :disabled="!hasSortChanges" @click="saveSortOrder">Сохранить порядок</AppButton>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <div class="sort-list">
            <div
                v-for="(item, index) in sortItems"
                :key="`${item.type}:${item.id}`"
                class="sort-item"
                draggable="true"
                @dragstart="dragKey = `${item.type}:${item.id}`"
                @dragend="dragKey = null"
                @dragover.prevent
                @drop="moveSortItem(`${item.type}:${item.id}`)"
            >
                <div class="sort-item__order">{{ index + 1 }}</div>
                <div class="sort-item__handle">::</div>
                <div class="sort-item__body">
                    <strong>{{ item.name }}</strong>
                    <div class="hint">{{ item.label }}<template v-if="item.code"> · {{ item.code }}</template></div>
                </div>
                <div class="sort-item__actions">
                    <AppButton
                        variant="secondary"
                        type="button"
                        :disabled="index === 0"
                        title="Переместить вверх"
                        @click="moveSortItemByOffset(index, -1)"
                    >↑</AppButton>
                    <AppButton
                        variant="secondary"
                        type="button"
                        :disabled="index === sortItems.length - 1"
                        title="Переместить вниз"
                        @click="moveSortItemByOffset(index, 1)"
                    >↓</AppButton>
                </div>
            </div>
        </div>
        <p v-if="!sortItems.length" class="empty-state">Групп для сортировки пока нет.</p>
    </section>
</template>

<style scoped>
.sort-list {
    display: grid;
    gap: 12px;
}

.sort-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.92);
    cursor: grab;
}

.sort-item__order {
    width: 32px;
    color: #64748b;
    font-weight: 700;
    text-align: center;
}

.sort-item__handle {
    font-weight: 700;
    color: #64748b;
    user-select: none;
}

.sort-item__body {
    display: grid;
    gap: 4px;
    flex: 1;
}

.sort-item__actions {
    display: flex;
    gap: 8px;
}
</style>
