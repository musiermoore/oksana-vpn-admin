<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    giveaways: Array,
});
</script>

<template>
    <Head title="Розыгрыши" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Розыгрыши</h1>
                <p>Здесь можно подготовить кампанию, включить автоповтор и посмотреть, как уже отработали прошлые конкурсы.</p>
            </div>
            <div class="actions">
                <AppButton href="/giveaways/create">Создать розыгрыш</AppButton>
            </div>
        </div>
    </section>

    <section class="stack">
        <article v-for="giveaway in giveaways" :key="giveaway.id" class="page-card stack">
            <div class="page-header">
                <div>
                    <h2>{{ giveaway.title }}</h2>
                    <p>{{ giveaway.description || 'Описание пока не добавлено.' }}</p>
                </div>
                <div class="actions">
                    <span class="badge">{{ giveaway.status_label }}</span>
                    <AppButton variant="secondary" :href="giveaway.links.edit">Открыть</AppButton>
                </div>
            </div>

            <div class="stat-grid">
                <article class="stat-card stack">
                    <p class="muted">Период</p>
                    <strong>{{ giveaway.starts_at }} - {{ giveaway.ends_at }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Участники</p>
                    <strong>{{ giveaway.stats.participants_count }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Вес</p>
                    <strong>{{ giveaway.stats.total_weight }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Призов</p>
                    <strong>{{ giveaway.stats.prizes_count }}</strong>
                </article>
            </div>
        </article>
    </section>
</template>

<style scoped>
.badge {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: rgba(219, 234, 254, 1);
    color: #1d4ed8;
    font-weight: 600;
}
</style>
