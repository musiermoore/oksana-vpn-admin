<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
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

    <AppPageHeader
        title="Транзакции"
        description="Операционная зона по движению денег: ожидающие действия, принятые списания и ручные корректировки."
        :stats="[
            { label: 'Текущий баланс', value: props.balance },
            { label: 'На рассмотрении', value: props.pending_transactions.length },
            { label: 'Принятые', value: props.transactions.length },
        ]"
    >
        <template #actions>
            <AppButton href="/transactions/create">Создать транзакцию</AppButton>
        </template>
    </AppPageHeader>

    <section v-if="pending_transactions.length" class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Ожидают решения</h2>
                <p>Транзакции, которые ещё не одобрены и требуют ручного подтверждения или отклонения.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Сумма</th>
                        <th>Баланс после</th>
                        <th>Одобрена</th>
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
                        <td>{{ transaction.amount }}<template v-if="transaction.type"> · {{ transaction.type.name }}</template></td>
                        <td>{{ transaction.current_balance_amount ?? '—' }}</td>
                        <td>{{ transaction.is_approved ? 'Да' : 'Нет' }}</td>
                        <td>{{ transaction.formatted_created_at }}</td>
                        <td>
                            <div class="actions">
                                <AppButton variant="success" type="button" @click="approve(transaction)">Принять</AppButton>
                                <AppButton variant="danger" type="button" @click="decline(transaction)">Отклонить</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Журнал проведённых операций</h2>
                <p>История уже сохранённых движений по балансу с возможностью перейти к редактированию записи.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Сумма</th>
                        <th>Баланс после</th>
                        <th>Одобрена</th>
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
                        <td>{{ transaction.amount }}<template v-if="transaction.type"> · {{ transaction.type.name }}</template></td>
                        <td>{{ transaction.current_balance_amount ?? '—' }}</td>
                        <td>{{ transaction.is_approved ? 'Да' : 'Нет' }}</td>
                        <td>{{ transaction.formatted_created_at }}</td>
                        <td>
                            <div class="actions">
                                <AppButton variant="secondary" :href="transaction.links.edit">Изменить</AppButton>
                                <AppButton variant="danger" type="button" @click="destroyTransaction(transaction)">Удалить</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
