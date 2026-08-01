<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            todo: 0,
            in_progress: 0,
            done: 0,
        }),
    },
});

const destroyTask = (task) => confirm(`Удалить задачу "${task.title}"?`) && router.delete(task.links.destroy);

const groupedTasks = computed(() => [
    {
        key: 'in_progress',
        title: 'В работе',
        count: props.stats.in_progress ?? 0,
        items: props.tasks.filter((task) => task.status === 'in_progress'),
    },
    {
        key: 'todo',
        title: 'Нужно сделать',
        count: props.stats.todo ?? 0,
        items: props.tasks.filter((task) => task.status === 'todo'),
    },
    {
        key: 'done',
        title: 'Готово',
        count: props.stats.done ?? 0,
        items: props.tasks.filter((task) => task.status === 'done'),
    },
]);
</script>

<template>
    <Head title="Задачи" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Задачи</h1>
                <p>Простой список того, что стоит сделать, с отдельными страницами на создание и редактирование.</p>
            </div>
            <div class="actions">
                <AppButton href="/tasks/create">Добавить задачу</AppButton>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card stack">
                <p class="muted">Всего</p>
                <h3>{{ stats.total }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Нужно сделать</p>
                <h3>{{ stats.todo }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">В работе</p>
                <h3>{{ stats.in_progress }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Готово</p>
                <h3>{{ stats.done }}</h3>
            </article>
        </div>
    </section>

    <section class="task-columns">
        <article v-for="group in groupedTasks" :key="group.key" class="task-column">
            <div class="task-column__header">
                <div>
                    <h2>{{ group.title }}</h2>
                    <p>{{ group.count }} задач</p>
                </div>
            </div>

            <div class="task-list">
                <article
                    v-for="task in group.items"
                    :key="task.id"
                    class="task-card"
                >
                    <Link class="task-card__body" :href="task.links.edit">
                        <div class="task-card__top">
                            <span class="task-status" :class="`task-status--${task.status}`">{{ task.status_label }}</span>
                            <span v-if="task.due_date_label" class="task-date">{{ task.due_date_label }}</span>
                        </div>
                        <h3>{{ task.title }}</h3>
                        <p>{{ task.description || 'Без описания' }}</p>
                    </Link>
                    <div class="task-card__actions">
                        <Link class="task-card__link" :href="task.links.edit">Открыть</Link>
                        <AppButton variant="danger" type="button" @click.prevent="destroyTask(task)">Удалить</AppButton>
                    </div>
                </article>

                <div v-if="!group.items.length" class="empty-state">Здесь пока пусто.</div>
            </div>
        </article>
    </section>
</template>

<style scoped>
.task-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.task-column {
    display: grid;
    gap: 16px;
    padding: 18px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 24px;
    background: rgba(248, 250, 252, 0.9);
}

.task-column__header h2,
.task-column__header p,
.task-card h3,
.task-card p {
    margin: 0;
}

.task-column__header p,
.task-card p,
.task-date {
    color: #64748b;
}

.task-list {
    display: grid;
    gap: 14px;
}

.task-card {
    display: grid;
    gap: 14px;
    padding: 18px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 20px;
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.12), transparent 30%),
        rgba(255, 255, 255, 0.95);
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
}

.task-card__body {
    display: grid;
    gap: 14px;
    color: inherit;
    text-decoration: none;
}

.task-card__top,
.task-card__actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.task-status {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
}

.task-status--todo {
    background: rgba(254, 249, 195, 1);
    color: #854d0e;
}

.task-status--in_progress {
    background: rgba(219, 234, 254, 1);
    color: #1d4ed8;
}

.task-status--done {
    background: rgba(220, 252, 231, 1);
    color: #166534;
}

.task-card__link {
    font-size: 0.92rem;
    color: #2563eb;
    font-weight: 600;
}

@media (max-width: 1100px) {
    .task-columns {
        grid-template-columns: 1fr;
    }
}
</style>
