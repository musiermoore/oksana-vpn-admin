<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    filters: Object,
    subscriptions: Array,
});
</script>

<template>
    <Head title="Подписки" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Подписки ({{ subscriptions.length }})</h1>
                <p>Текущие и завершённые подписки участников.</p>
            </div>
        </div>

        <div class="tabs">
            <Link class="chip" :class="{ 'is-active': !filters.old }" href="/subscriptions">Активные</Link>
            <Link class="chip" :class="{ 'is-active': filters.old }" href="/subscriptions?old=1">Старые</Link>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Участник</th>
                    <th>Telegram</th>
                    <th>Период</th>
                    <th>Стоимость</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="subscription in subscriptions" :key="subscription.id">
                    <td>
                        <Link
                            v-if="subscription.user?.is_active"
                            :href="subscription.user.edit_url"
                        >
                            {{ subscription.user.full_name }}
                        </Link>
                        <span v-else>{{ subscription.user?.full_name || '—' }}</span>
                    </td>
                    <td>{{ subscription.user?.telegram || '—' }}</td>
                    <td>{{ subscription.start_date }} - {{ subscription.end_date }}</td>
                    <td>{{ subscription.price }}</td>
                    <td>
                        <span class="badge" :class="subscription.is_active ? 'badge--success' : ''">
                            {{ subscription.is_active ? 'Активна' : 'Истекла' }}
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="subscription.links.edit">Изменить</Link>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
