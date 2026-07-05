<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    tax_logs: {
        type: Array,
        default: () => [],
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
        second: '2-digit',
    }).format(new Date(value));
};

const stringify = (value) => JSON.stringify(value ?? null, null, 2);
const taxStatusLabel = (status) => ({
    not_sent: 'Не отправлен',
    queued: 'В очереди',
    sending: 'Отправляется',
    sent: 'Отправлен',
    failed: 'Ошибка',
}[status] ?? status);
</script>

<template>
    <Head :title="`Invoice #${invoice.id}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Invoice #{{ invoice.id }}</h1>
                <p>
                    {{ invoice.amount }} {{ invoice.currency }}
                    <template v-if="invoice.user?.full_name"> · {{ invoice.user.full_name }}</template>
                </p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" href="/invoices">Назад к списку</Link>
                <Link class="button" :href="invoice.links.send_preview">Отправить в налоговую</Link>
            </div>
        </div>

        <div class="grid grid--two">
            <article class="page-card stack">
                <h2 class="section-title">Оплата</h2>
                <div class="kv-list">
                    <div class="kv-row"><span>ID платежа</span><strong>{{ invoice.provider_payment_id }}</strong></div>
                    <div class="kv-row"><span>Провайдер</span><strong>{{ invoice.provider }}</strong></div>
                    <div class="kv-row"><span>Статус оплаты</span><strong>{{ invoice.paid ? 'Оплачен' : 'Не оплачен' }}</strong></div>
                    <div class="kv-row"><span>Статус провайдера</span><strong>{{ invoice.status }}</strong></div>
                    <div class="kv-row"><span>Оплачен в</span><strong>{{ formatDate(invoice.paid_at) }}</strong></div>
                </div>
            </article>

            <article class="page-card stack">
                <h2 class="section-title">Налоговая</h2>
                <div class="kv-list">
                    <div class="kv-row"><span>Статус отправки</span><strong>{{ taxStatusLabel(invoice.tax_status) }}</strong></div>
                    <div class="kv-row"><span>Сервис</span><strong>{{ invoice.tax_service_name || '—' }}</strong></div>
                    <div class="kv-row"><span>Предположительная комиссия</span><strong>{{ invoice.tax_estimated_commission }} {{ invoice.currency }}</strong></div>
                    <div class="kv-row"><span>Receipt UUID</span><strong>{{ invoice.tax_receipt_uuid || '—' }}</strong></div>
                    <div class="kv-row"><span>Отправлен в</span><strong>{{ formatDate(invoice.tax_sent_at) }}</strong></div>
                </div>

                <div v-if="invoice.tax_error_message" class="alert alert--danger">
                    {{ invoice.tax_error_message }}
                </div>
            </article>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Транзакции инвойса</h2>
                <p>Эти транзакции попадут в payload при отправке в налоговую.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Сумма</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="transaction in invoice.transactions" :key="transaction.id">
                        <td>#{{ transaction.id }}</td>
                        <td>{{ transaction.amount }}</td>
                        <td>{{ transaction.type?.name || '—' }}</td>
                        <td>{{ transaction.is_approved ? 'Принята' : 'На рассмотрении' }}</td>
                        <td>{{ transaction.formatted_created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section v-if="tax_logs.length" class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">История отправок</h2>
            </div>
        </div>

        <div class="stack">
            <article v-for="log in tax_logs" :key="log.id" class="page-card stack">
                <div class="page-header">
                    <div>
                        <h3 class="section-title">{{ log.action }}</h3>
                        <p>{{ log.method }} {{ log.endpoint }} · {{ log.status }} · {{ formatDate(log.completed_at || log.started_at || log.queued_at) }}</p>
                    </div>
                </div>

                <pre class="code-block">{{ stringify(log.response_json || log.response_body || log.error_message) }}</pre>
            </article>
        </div>
    </section>
</template>
