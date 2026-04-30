<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    users: Array,
    current_payments: Array,
    active_period_id: Number,
});

const form = useForm({
    user_id: '',
    current_payment_id: props.active_period_id ?? props.current_payments[0]?.id ?? '',
    amount: '',
});
</script>

<template>
    <Head title="Создание дополнительной оплаты" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создание дополнительной оплаты</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Участник</span>
                <select v-model="form.user_id">
                    <option value="">Участник не выбран</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Период</span>
                <select v-model="form.current_payment_id">
                    <option v-for="payment in current_payments" :key="payment.id" :value="payment.id">
                        {{ payment.full_date }}{{ payment.id === active_period_id ? ' (Активный)' : '' }}
                    </option>
                </select>
            </label>

            <label class="field">
                <span>Сумма</span>
                <input v-model="form.amount" type="number" step="0.01" required>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/extra-payments">Назад</Link>
            </div>
        </form>
    </section>
</template>
