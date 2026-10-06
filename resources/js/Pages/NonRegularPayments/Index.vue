<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({ payments: Array });

const destroyPayment = (payment) => confirm('Удалить нерегулярный расход?') && router.delete(payment.links.destroy);
</script>

<template>
    <Head title="Нерегулярные расходы" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Нерегулярные расходы</h1></div>
            <AppButton href="/non-regular-payments/create">Создать</AppButton>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Сумма</th><th>Описание</th><th>Создано</th><th>Обновлено</th><th>Действия</th></tr></thead>
            <tbody>
                <tr v-for="payment in payments" :key="payment.id">
                    <td>{{ payment.id }}</td>
                    <td>{{ payment.amount }}</td>
                    <td>{{ payment.description }}</td>
                    <td>{{ payment.created_at }}</td>
                    <td>{{ payment.updated_at }}</td>
                    <td><AppButton variant="danger" type="button" @click="destroyPayment(payment)">Удалить</AppButton></td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
