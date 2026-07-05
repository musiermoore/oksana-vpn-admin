<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
});

const form = useForm({});
const submit = () => form.post(props.invoice.links.send);

const taxStatusLabel = (status) => ({
    not_sent: 'Не отправлен',
    queued: 'В очереди',
    sending: 'Отправляется',
    sent: 'Отправлен',
    failed: 'Ошибка',
}[status] ?? status);
</script>

<template>
    <Head :title="`Send Invoice #${invoice.id}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Отправить Invoice #{{ invoice.id }} в налоговую</h1>
                <p>Проверьте сумму, транзакции и текущий статус перед постановкой в очередь.</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" :href="invoice.links.show">К деталям</Link>
            </div>
        </div>

        <div class="grid grid--two">
            <article class="page-card stack">
                <h2 class="section-title">Сводка</h2>
                <div class="kv-list">
                    <div class="kv-row"><span>Сумма</span><strong>{{ invoice.amount }} {{ invoice.currency }}</strong></div>
                    <div class="kv-row"><span>Оплата</span><strong>{{ invoice.paid ? 'Оплачен' : 'Не оплачен' }}</strong></div>
                    <div class="kv-row"><span>Статус налоговой</span><strong>{{ taxStatusLabel(invoice.tax_status) }}</strong></div>
                    <div class="kv-row"><span>Сервис</span><strong>{{ invoice.tax_service_name || 'Настройка сетевой конфигурации' }}</strong></div>
                    <div class="kv-row"><span>Предположительная комиссия</span><strong>{{ invoice.tax_estimated_commission }} {{ invoice.currency }}</strong></div>
                </div>
            </article>

            <article class="page-card stack">
                <h2 class="section-title">Что отправится</h2>
                <p class="muted">Короткий список транзакций для чека.</p>
                <div class="stack">
                    <div v-for="transaction in invoice.transactions" :key="transaction.id" class="item-row">
                        <strong>#{{ transaction.id }}</strong>
                        <span>{{ transaction.amount }} · {{ transaction.type?.name || 'Без типа' }}</span>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="invoice.tax_error_message" class="alert alert--danger">
            Последняя ошибка: {{ invoice.tax_error_message }}
        </div>

        <div class="actions">
            <button class="button" type="button" :disabled="form.processing || !invoice.paid" @click="submit">
                Отправить в налоговую
            </button>
        </div>
    </section>
</template>
