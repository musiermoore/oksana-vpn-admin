<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    referrers: Array,
});
</script>

<template>
    <Head title="Реферальная программа" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Реферальная программа</h1>
                <p>Сводка по пригласившим, активным рефералам и скидкам.</p>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Приглашено</th>
                    <th>Активных</th>
                    <th>Накопительная</th>
                    <th>Постоянная</th>
                    <th>Итого</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="referrer in referrers" :key="referrer.id">
                    <td>
                        <strong>{{ referrer.telegram || '—' }}</strong>
                        <div>{{ referrer.name }}</div>
                    </td>
                    <td>{{ referrer.invited_count }}</td>
                    <td>{{ referrer.active_referrals_count }}</td>
                    <td>{{ referrer.accumulated_discount_percent }}%</td>
                    <td>{{ referrer.permanent_discount_percent }}%</td>
                    <td>{{ referrer.total_discount_percent }}%</td>
                    <td>
                        <Link class="button button--secondary" :href="referrer.show_url">Открыть</Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
