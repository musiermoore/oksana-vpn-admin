<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    referrer: Object,
    referrals: Array,
});
</script>

<template>
    <Head :title="`Рефералы ${referrer.telegram || referrer.name}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ referrer.telegram || referrer.name }}</h1>
                <p>Детали по приглашённым пользователям и прогрессу скидки.</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" href="/referrals">Назад</Link>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <span>Активных</span>
                <strong>{{ referrer.active_referrals_count }}</strong>
            </div>
            <div class="stat-card">
                <span>Следующий уровень</span>
                <strong>{{ referrer.next_level_active_referrals ?? 'Максимум' }}</strong>
            </div>
            <div class="stat-card">
                <span>Осталось</span>
                <strong>{{ referrer.remaining_to_next_level }}</strong>
            </div>
            <div class="stat-card">
                <span>Текущая скидка</span>
                <strong>{{ referrer.total_discount_percent }}%</strong>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Активность</th>
                    <th>Подписка</th>
                    <th>Статус награды</th>
                    <th>Скидка</th>
                    <th>Бонус дней</th>
                    <th>Создано</th>
                    <th>Начислено</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in referrals" :key="item.id">
                    <td>
                        <strong>{{ item.telegram || '—' }}</strong>
                        <div>{{ item.name || 'Без имени' }}</div>
                    </td>
                    <td>{{ item.is_active ? 'Активен' : 'Неактивен' }}</td>
                    <td>{{ item.subscription_expires_at || 'Нет активной' }}</td>
                    <td>{{ item.reward_status }}</td>
                    <td>{{ item.reward_percent }}%</td>
                    <td>+{{ item.bonus_days }}</td>
                    <td>{{ item.created_at }}</td>
                    <td>{{ item.rewarded_at || '—' }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
