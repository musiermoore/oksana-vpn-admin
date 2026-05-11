<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    transaction: Object,
    users: Array,
    types: Array,
});

const chargeTypeSlugs = ['subscription', 'extra-payment'];

const form = useForm({
    user_id: props.transaction?.user?.id ?? props.users[0]?.id ?? '',
    type_id: props.transaction?.type?.id ?? props.types[0]?.id ?? '',
    amount: props.transaction?.amount ? Math.abs(props.transaction.amount) : '',
    description: props.transaction?.description ?? '',
    is_approved: true
});

const submit = () => props.mode === 'edit' ? form.put(props.submit_url) : form.post(props.submit_url);

const selectedType = () => props.types.find((type) => type.id === form.type_id);
const amountHint = () => chargeTypeSlugs.includes(selectedType()?.slug)
    ? 'Введите положительную сумму. Списание сохранится как отрицательная транзакция.'
    : 'Введите положительную сумму пополнения.';
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование транзакции' : 'Создание транзакции'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование транзакции' : 'Создание транзакции' }}</h1></div></div>

        <form @submit.prevent="submit">
            <div class="grid grid--two">
                <label class="field">
                    <span>Участник</span>
                    <select v-model="form.user_id">
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                    </select>
                </label>

                <label class="field">
                    <span>Тип</span>
                    <select v-model="form.type_id">
                        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                </label>
            </div>

            <label class="field">
                <span>Сумма</span>
                <input v-model="form.amount" type="number" min="0" step="0.01" required>
                <small class="field-hint">{{ amountHint() }}</small>
            </label>

            <label class="field">
                <span>Описание</span>
                <textarea v-model="form.description" rows="4" />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/transactions">Назад</Link>
            </div>
        </form>
    </section>
</template>

<style scoped>
form {
    display: flex;
    flex-direction: column;
    gap: 10px 0;
}
</style>
