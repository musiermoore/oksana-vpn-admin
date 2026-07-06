<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    tax_status_options: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    tax_status: props.invoice.tax_status,
});

const submit = () => form.patch(props.invoice.links.update_tax_status);

const taxStatusLabel = (status) => ({
    not_sent: 'Не отправлен',
    queued: 'В очереди',
    sending: 'Отправляется',
    sent: 'Отправлен',
    failed: 'Ошибка',
}[status] ?? status);
</script>

<template>
    <Head :title="`Edit Invoice #${invoice.id}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Редактирование Invoice #{{ invoice.id }}</h1>
                <p>Здесь можно вручную скорректировать статус налоговой, если чек уже был создан вне системы или статус сохранился некорректно.</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" :href="invoice.links.show">К деталям</Link>
                <Link class="button button--secondary" href="/invoices">К списку</Link>
            </div>
        </div>

        <div class="grid grid--two">
            <article class="page-card stack">
                <h2 class="section-title">Сводка</h2>
                <div class="kv-list">
                    <div class="kv-row"><span>Сумма</span><strong>{{ invoice.amount }} {{ invoice.currency }}</strong></div>
                    <div class="kv-row"><span>Оплата</span><strong>{{ invoice.paid ? 'Оплачен' : 'Не оплачен' }}</strong></div>
                    <div class="kv-row"><span>Текущий статус налоговой</span><strong>{{ taxStatusLabel(invoice.tax_status) }}</strong></div>
                    <div class="kv-row"><span>Receipt UUID</span><strong>{{ invoice.tax_receipt_uuid || '—' }}</strong></div>
                </div>
            </article>

            <article class="page-card stack">
                <h2 class="section-title">Изменить статус налоговой</h2>

                <form class="stack" @submit.prevent="submit">
                    <label class="field">
                        <span>Статус налоговой</span>
                        <select v-model="form.tax_status">
                            <option v-for="status in tax_status_options" :key="status" :value="status">
                                {{ taxStatusLabel(status) }}
                            </option>
                        </select>
                        <small v-if="form.errors.tax_status" class="field-error">{{ form.errors.tax_status }}</small>
                    </label>

                    <div class="actions">
                        <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                    </div>
                </form>
            </article>
        </div>
    </section>
</template>
