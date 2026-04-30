<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    payments: Array,
});

const destroyPayment = (payment) => confirm('Удалить дополнительную оплату?') && router.delete(payment.links.destroy);
</script>

<template>
    <Head title="Дополнительные оплаты" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Дополнительные оплаты</h1></div>
            <div class="actions">
                <Link class="button" href="/extra-payments/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Участник</th>
                    <th>Период</th>
                    <th>Сумма</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payment in payments" :key="payment.id">
                    <td>
                        <Link v-if="payment.user?.is_active" :href="payment.user.edit_url">{{ payment.user.full_name }}</Link>
                        <span v-else>{{ payment.user?.full_name }}</span>
                    </td>
                    <td>{{ payment.current_payment?.full_date }}</td>
                    <td>{{ payment.amount }}</td>
                    <td>
                        <button class="button button--danger" type="button" @click="destroyPayment(payment)">Удалить</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
