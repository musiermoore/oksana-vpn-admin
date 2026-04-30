<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    current_payments: Array,
});

const destroyPayment = (payment) => confirm('Удалить период оплаты?') && router.delete(payment.links.destroy);
</script>

<template>
    <Head title="Периоды оплаты" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Периоды оплаты</h1>
            </div>
            <div class="actions">
                <Link class="button" href="/current-payments/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Начало</th>
                    <th>Конец</th>
                    <th>Сумма</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payment in current_payments" :key="payment.id">
                    <td>{{ payment.start_date }}</td>
                    <td>{{ payment.end_date }}</td>
                    <td>{{ payment.amount }}</td>
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="payment.links.edit">Изменить</Link>
                            <button class="button button--danger" type="button" @click="destroyPayment(payment)">Удалить</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
