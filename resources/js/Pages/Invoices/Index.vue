<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    invoices: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            paid: 0,
            sent_to_tax: 0,
            queued_to_tax: 0,
        }),
    },
});

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const taxStatusLabel = (status) => ({
    not_sent: 'Не отправлен',
    queued: 'В очереди',
    sending: 'Отправляется',
    sent: 'Отправлен',
    failed: 'Ошибка',
}[status] ?? status);
</script>

<template>
    <Head title="Invoices" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Invoices</h1>
                <p>Все платежи с оплатой, налоговым статусом и быстрым переходом к деталям.</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" href="/tax-settings">Настройки налоговой</Link>
                <Link class="button" href="/tax-debug">Tax Debug</Link>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card stack">
                <p class="muted">Всего</p>
                <h3>{{ stats.total }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Оплачено</p>
                <h3>{{ stats.paid }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Отправлено в налоговую</p>
                <h3>{{ stats.sent_to_tax }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">В очереди</p>
                <h3>{{ stats.queued_to_tax }}</h3>
            </article>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Сумма</th>
                    <th>Статус оплаты</th>
                    <th>Статус налоговой</th>
                    <th>Комиссия</th>
                    <th>Когда</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="invoice in invoices" :key="invoice.id">
                    <td>#{{ invoice.id }}</td>
                    <td>
                        <Link v-if="invoice.user?.edit_url" :href="invoice.user.edit_url">{{ invoice.user.full_name }}</Link>
                        <span v-else>—</span>
                    </td>
                    <td>{{ invoice.amount }} {{ invoice.currency }}</td>
                    <td>
                        <span class="status-pill" :class="invoice.paid ? 'status-pill--success' : 'status-pill--muted'">
                            {{ invoice.paid ? 'Оплачен' : 'Не оплачен' }}
                        </span>
                    </td>
                    <td>
                        <span
                            class="status-pill"
                            :class="{
                                'status-pill--success': invoice.tax_status === 'sent',
                                'status-pill--warning': ['queued', 'sending'].includes(invoice.tax_status),
                                'status-pill--danger': invoice.tax_status === 'failed',
                                'status-pill--muted': invoice.tax_status === 'not_sent',
                            }"
                        >
                            {{ taxStatusLabel(invoice.tax_status) }}
                        </span>
                    </td>
                    <td>{{ invoice.tax_estimated_commission }} {{ invoice.currency }}</td>
                    <td>{{ formatDate(invoice.paid_at || invoice.created_at) }}</td>
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="invoice.links.show">Открыть</Link>
                            <Link class="button" :href="invoice.links.send_preview">В налоговую</Link>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="!invoices.length" class="empty-state">Инвойсов пока нет.</div>
    </section>
</template>
