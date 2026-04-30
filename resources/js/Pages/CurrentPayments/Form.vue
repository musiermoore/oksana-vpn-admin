<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    current_payment: Object,
});

const form = useForm({
    start_date: props.current_payment.start_date,
    end_date: props.current_payment.end_date,
    amount: props.current_payment.amount,
});

const submit = () => props.mode === 'edit' ? form.put(props.submit_url) : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование периода' : 'Создание периода оплаты'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование периода' : 'Создание периода оплаты' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Дата начала</span>
                <input v-model="form.start_date" type="date">
            </label>

            <label class="field">
                <span>Дата окончания</span>
                <input v-model="form.end_date" type="date">
            </label>

            <label class="field">
                <span>Сумма</span>
                <input v-model="form.amount" type="number" step="0.01" required>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/current-payments">Назад</Link>
            </div>
        </form>
    </section>
</template>
