<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    filters: Object,
    tickets: Array,
});

const statusLabel = (status) => ({
    open: 'Открыт',
    answered: 'Отвечен',
    closed: 'Закрыт',
}[status] || status);
</script>

<template>
    <Head title="Поддержка" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Тикеты поддержки</h1>
                <p>Обращения из Telegram mini-app с быстрым переходом в чат пользователя.</p>
            </div>
        </div>

        <div class="tabs">
            <Link class="chip" :class="{ 'is-active': !filters.status }" href="/support-tickets">Все</Link>
            <Link class="chip" :class="{ 'is-active': filters.status === 'open' }" href="/support-tickets?status=open">Открытые</Link>
            <Link class="chip" :class="{ 'is-active': filters.status === 'answered' }" href="/support-tickets?status=answered">Отвеченные</Link>
            <Link class="chip" :class="{ 'is-active': filters.status === 'closed' }" href="/support-tickets?status=closed">Закрытые</Link>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Тема</th>
                    <th>Статус</th>
                    <th>Последнее сообщение</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="ticket in tickets" :key="ticket.id">
                    <td>#{{ ticket.id }}</td>
                    <td>{{ ticket.user?.telegram || ticket.user?.name || '—' }}</td>
                    <td>{{ ticket.subject || 'Без темы' }}</td>
                    <td>{{ statusLabel(ticket.status) }}</td>
                    <td>{{ ticket.latest_message?.message || '—' }}</td>
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="ticket.links.show">Открыть</Link>
                            <a
                                v-if="ticket.user?.chat_url"
                                class="button"
                                :href="ticket.user.chat_url"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Открыть чат
                            </a>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
