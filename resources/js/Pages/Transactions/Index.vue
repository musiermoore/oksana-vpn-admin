<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    balance: Number,
    transactions: Array,
    pending_transactions: Array,
});

const approve = (transaction) => router.post(transaction.links.approve);
const decline = (transaction) => confirm('Отклонить транзакцию?') && router.delete(transaction.links.decline);
const destroyTransaction = (transaction) => confirm('Удалить транзакцию?') && router.delete(transaction.links.destroy);
</script>

<template>
    <Head title="Транзакции" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Транзакции</h1>
                <p>Текущий баланс: {{ balance }}</p>
            </div>
            <div class="actions">
                <Link class="button" href="/transactions/create">Создать</Link>
            </div>
        </div>
    </section>

    <section v-if="pending_transactions.length" class="page-card stack">
        <div class="page-header"><div><h2 class="section-title">На рассмотрении</h2></div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Сумма</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="transaction in pending_transactions" :key="transaction.id">
                        <td>
                            <Link v-if="transaction.user?.is_active" :href="transaction.user.edit_url">{{ transaction.user.full_name }}</Link>
                            <span v-else>{{ transaction.user?.full_name }}</span>
                        </td>
                        <td>{{ transaction.amount }}</td>
                        <td>{{ transaction.formatted_created_at }}</td>
                        <td>
                            <div class="actions">
                                <button class="button button--success" type="button" @click="approve(transaction)">Принять</button>
                                <button class="button button--danger" type="button" @click="decline(transaction)">Отклонить</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header"><div><h2 class="section-title">Принятые</h2></div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Сумма</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="transaction in transactions" :key="transaction.id">
                        <td>
                            <Link v-if="transaction.user?.is_active" :href="transaction.user.edit_url">{{ transaction.user.full_name }}</Link>
                            <span v-else>{{ transaction.user?.full_name }}</span>
                        </td>
                        <td>{{ transaction.amount }}</td>
                        <td>{{ transaction.formatted_created_at }}</td>
                        <td>
                            <div class="actions">
                                <Link class="button button--secondary" :href="transaction.links.edit">Изменить</Link>
                                <button class="button button--danger" type="button" @click="destroyTransaction(transaction)">Удалить</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
