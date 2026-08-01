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

const userOptions = [
    { label: 'Участник не выбран', value: '' },
    ...props.users.map((user) => ({ label: user.full_name, value: user.id })),
];

const paymentOptions = props.current_payments.map((payment) => ({
    label: `${payment.full_date}${payment.id === props.active_period_id ? ' (Активный)' : ''}`,
    value: payment.id,
}));
</script>

<template>
    <Head title="Создание дополнительной оплаты" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создание дополнительной оплаты</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Участник</span>
                <AppSelect v-model="form.user_id" :options="userOptions" />
            </label>

            <label class="field">
                <span>Период</span>
                <AppSelect v-model="form.current_payment_id" :options="paymentOptions" />
            </label>

            <label class="field">
                <span>Сумма</span>
                <AppInput v-model="form.amount" type="number" step="0.01" required />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/extra-payments">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
