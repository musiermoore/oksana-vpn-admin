<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    query: {
        type: String,
        default: '',
    },
    results: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Поиск" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Поиск по админке</h1>
                <p v-if="query">Запрос: <strong>{{ query }}</strong></p>
                <p v-else>Введите запрос в верхней строке поиска.</p>
            </div>
        </div>

        <div v-if="!query" class="empty-state">
            Ищите по ID, username, UUID, IP, названию сервера, конфигу или транзакции.
        </div>

        <div v-else-if="!results.length" class="empty-state">
            По запросу ничего не найдено.
        </div>
    </section>

    <section v-if="results.length" class="search-results">
        <article v-for="section in results" :key="section.label" class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">{{ section.label }}</h2>
                    <p>{{ section.items.length }} результатов</p>
                </div>
            </div>

            <div class="list">
                <a
                    v-for="item in section.items"
                    :key="`${section.label}-${item.href}-${item.title}`"
                    :href="item.href"
                    class="item-row item-row--search"
                >
                    <div>
                        <strong>{{ item.title }}</strong>
                        <div class="muted">{{ item.description }}</div>
                    </div>
                    <span class="badge">{{ item.meta }}</span>
                </a>
            </div>
        </article>
    </section>
</template>

<style scoped>
.search-results {
    display: grid;
    gap: 18px;
}

.item-row--search {
    color: inherit;
}

.item-row--search strong {
    display: block;
    margin-bottom: 4px;
}
</style>
