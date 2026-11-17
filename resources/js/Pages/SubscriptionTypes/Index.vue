<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    types: { type: Array, default: () => [] },
});

const deleteType = (type) => {
    if (type.is_system) return;
    if (window.confirm('Удалить тип «' + type.name + '»?')) {
        router.delete('/subscription-types/' + type.id);
    }
};
</script>

<template>
    <Head title="Типы подписки" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Типы подписки</h1>
                <p>Управляйте ссылками подписки, их составом и порядком.</p>
            </div>
            <AppButton href="/subscription-types/create">Создать тип</AppButton>
        </div>

        <div v-if="types.length" class="subscription-type-list">
            <article v-for="type in types" :key="type.id" class="subscription-type-card">
                <div class="subscription-type-copy">
                    <div class="subscription-type-title">
                        <h2>{{ type.name }}</h2>
                        <span class="type-state" :class="{ 'is-active': type.is_active }">
                            {{ type.is_active ? 'Включена' : 'Отключена' }}
                        </span>
                        <span v-if="type.is_system" class="type-state">Системная</span>
                    </div>
                    <code>/subscription/{{ type.slug }}</code>
                    <p>{{ type.kind_label }} · {{ type.format }} · {{ type.items_count }} источников</p>
                </div>
                <div class="subscription-type-actions">
                    <AppButton variant="secondary" :href="'/subscription-types/' + type.id + '/edit'">Изменить</AppButton>
                    <AppButton
                        v-if="!type.is_system"
                        variant="danger"
                        type="button"
                        @click="deleteType(type)"
                    >
                        Удалить
                    </AppButton>
                </div>
            </article>
        </div>
        <p v-else class="empty-state">Типов подписки пока нет. Создайте первый, чтобы собрать ссылку.</p>
    </section>
</template>

<style scoped>
.subscription-type-list { display: grid; gap: 12px; }
.subscription-type-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    border: 1px solid rgba(148, 163, 184, .32);
    border-radius: 12px;
    background: rgba(255, 255, 255, .76);
}
.subscription-type-copy { display: grid; gap: 6px; }
.subscription-type-title,
.subscription-type-actions { display: flex; align-items: center; gap: 10px; }
.subscription-type-title h2 { margin: 0; font-size: 1.05rem; }
.subscription-type-copy p { margin: 0; color: var(--muted, #687386); }
.type-state { padding: 3px 8px; border-radius: 999px; background: rgba(148, 163, 184, .18); color: var(--muted, #687386); font-size: .78rem; }
.type-state.is-active { background: rgba(74, 168, 129, .14); color: #357b61; }

@media (max-width: 650px) {
    .subscription-type-card { align-items: stretch; flex-direction: column; }
    .subscription-type-actions { justify-content: flex-end; }
    .subscription-type-title { flex-wrap: wrap; }
}
</style>
