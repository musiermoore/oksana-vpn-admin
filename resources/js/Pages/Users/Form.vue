<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    user: Object,
    payments: Array,
});

const form = useForm({
    name: props.user?.name ?? '',
    telegram: props.user?.telegram ?? '',
    extra_payment: props.user?.extra_payment ?? 0,
    description: props.user?.description ?? '',
    join_at: props.user?.join_at ?? props.payments[0]?.start_date ?? '',
    create_configs: true,
    is_active: props.user?.is_active ?? true,
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};

const toggleConfig = (config) => router.post(config.is_active ? config.links.disable : config.links.enable);
const destroyConfig = (config) => confirm(`Удалить конфиг ${config.name}?`) && router.delete(config.links.destroy);
const approveTransaction = (transaction) => router.post(transaction.links.approve);
const declineTransaction = (transaction) => confirm('Отклонить транзакцию?') && router.delete(transaction.links.decline);
const destroyTransaction = (transaction) => confirm('Удалить транзакцию?') && router.delete(transaction.links.destroy);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование участника' : 'Создание участника'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? 'Редактирование участника' : 'Создание участника' }}</h1>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label v-if="mode === 'edit'" class="field">
                <span>Активен</span>
                <input v-model="form.is_active" type="checkbox">
            </label>

            <label class="field">
                <span>Имя</span>
                <input v-model="form.name" type="text" required>
            </label>

            <label class="field">
                <span>Telegram</span>
                <input v-model="form.telegram" type="text" required>
            </label>

            <label class="field">
                <span>Доп. оплата</span>
                <input v-model="form.extra_payment" type="number" required>
            </label>

            <label class="field">
                <span>Дата присоединения</span>
                <select v-model="form.join_at">
                    <option v-for="payment in payments" :key="payment.id" :value="payment.start_date">
                        {{ payment.formatted_start_date }} ({{ payment.amount }}₽)
                    </option>
                </select>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <textarea v-model="form.description" />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Дефолтные конфиги</span>
                <input v-model="form.create_configs" type="checkbox">
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/users">Назад</Link>
            </div>
        </form>
    </section>

    <template v-if="mode === 'edit' && user">
        <section class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">Конфиги</h2>
                </div>
                <div class="actions">
                    <Link class="button" href="/configs/create">Создать</Link>
                </div>
            </div>

            <div v-if="user.configs.length" class="list">
                <div v-for="config in user.configs" :key="config.id" class="item-row">
                    <Link :href="config.links.edit">{{ config.server.code }}: {{ config.name }}</Link>
                    <div class="actions">
                        <Link class="button button--secondary" :href="config.links.edit">Открыть</Link>
                        <button
                            class="button"
                            :class="config.is_active ? 'button--danger' : 'button--success'"
                            type="button"
                            @click="toggleConfig(config)"
                        >
                            {{ config.is_active ? 'Отключить' : 'Включить' }}
                        </button>
                        <button class="button button--danger" type="button" @click="destroyConfig(config)">Удалить</button>
                    </div>
                </div>
            </div>
            <div v-else class="empty-state">У пользователя пока нет конфигов.</div>
        </section>

        <section class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">Транзакции</h2>
                </div>
                <div class="actions">
                    <Link class="button" href="/transactions/create">Создать</Link>
                </div>
            </div>

            <div v-if="user.transactions.length" class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Сумма</th>
                            <th>Дата</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in user.transactions" :key="transaction.id">
                            <td>{{ transaction.amount }}</td>
                            <td>{{ transaction.formatted_created_at }}</td>
                            <td>
                                <div v-if="transaction.is_approved" class="actions">
                                    <span class="badge badge--success">Принята</span>
                                    <Link class="button button--secondary" :href="transaction.links.edit">Изменить</Link>
                                    <button class="button button--danger" type="button" @click="destroyTransaction(transaction)">Удалить</button>
                                </div>
                                <div v-else class="actions">
                                    <span class="badge">На рассмотрении</span>
                                    <button class="button button--success" type="button" @click="approveTransaction(transaction)">Принять</button>
                                    <button class="button button--danger" type="button" @click="declineTransaction(transaction)">Отклонить</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">Транзакций пока нет.</div>
        </section>
    </template>
</template>
