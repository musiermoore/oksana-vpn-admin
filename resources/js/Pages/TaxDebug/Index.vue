<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    presets: {
        type: Array,
        default: () => [],
    },
    initial_form: {
        type: Object,
        default: () => ({
            preset: 'auth',
            invoice_id: 0,
        }),
    },
    logs: {
        type: Array,
        default: () => [],
    },
    invoices: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    preset: props.initial_form.preset,
    invoice_id: props.initial_form.invoice_id,
});

const presetMap = computed(() => Object.fromEntries(props.presets.map((preset) => [preset.value, preset])));
const currentPreset = computed(() => presetMap.value[form.preset] ?? null);

watch(
    () => form.preset,
    (value) => {
        if (value !== 'income') {
            form.invoice_id = 0;
        }
    },
);

const submit = () => form.post('/tax-debug', { preserveScroll: true });
const stringify = (value) => JSON.stringify(value ?? null, null, 2);

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
</script>

<template>
    <Head title="Tax Debug" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Tax Debug</h1>
                <p>Очередной mini-Postman для Moy Nalog с пресетами `Auth`, `User`, `Income / Receipts`.</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" href="/tax-settings">Настройки</Link>
                <Link class="button" href="/invoices">Invoices</Link>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Запрос</h2>
                <p v-if="currentPreset">{{ currentPreset.action }} · {{ currentPreset.method }} {{ currentPreset.endpoint }}</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <div class="grid grid--two">
                <label class="field">
                    <span>Preset</span>
                    <select v-model="form.preset">
                        <option v-for="preset in presets" :key="preset.value" :value="preset.value">
                            {{ preset.label }}
                        </option>
                    </select>
                </label>

                <label class="field">
                    <span>Invoice</span>
                    <select v-model="form.invoice_id" :disabled="form.preset !== 'income'">
                        <option :value="0">Не нужен</option>
                        <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">{{ invoice.label }}</option>
                    </select>
                    <small class="muted">Нужен только для `Income / Receipts`.</small>
                </label>
            </div>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Поставить в очередь</button>
            </div>
        </form>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Последние результаты</h2>
                <p>Статусы и ответы фоновых запросов к Moy Nalog.</p>
            </div>
        </div>

        <div v-if="logs.length" class="stack">
            <article v-for="log in logs" :key="log.id" class="page-card stack">
                <div class="page-header">
                    <div>
                        <h3 class="section-title">{{ log.action }}</h3>
                        <p>
                            {{ log.method }} {{ log.endpoint }} · {{ log.status }}
                            <template v-if="log.response_status"> · HTTP {{ log.response_status }}</template>
                            <template v-if="log.invoice?.show_url"> · <Link :href="log.invoice.show_url">Invoice #{{ log.invoice.id }}</Link></template>
                        </p>
                    </div>
                </div>

                <div class="grid grid--two">
                    <article class="page-card stack">
                        <h4 class="section-title">Request</h4>
                        <div class="muted">Queued: {{ formatDate(log.queued_at) }}</div>
                        <pre class="code-block">{{ stringify(log.request_payload) }}</pre>
                    </article>

                    <article class="page-card stack">
                        <h4 class="section-title">Response</h4>
                        <div class="muted">Done: {{ formatDate(log.completed_at || log.started_at) }}</div>
                        <pre class="code-block">{{ stringify(log.response_json || log.response_body || log.error_message) }}</pre>
                    </article>
                </div>
            </article>
        </div>

        <div v-else class="empty-state">Запусков пока не было.</div>
    </section>
</template>
