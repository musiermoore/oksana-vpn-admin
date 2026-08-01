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
const userOptions = props.users.map((user) => ({
    label: user.full_name,
    value: user.id,
}));
const typeOptions = props.types.map((type) => ({
    label: type.name,
    value: type.id,
}));

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
                    <AppSelect v-model="form.user_id" :options="userOptions" />
                </label>

                <label class="field">
                    <span>Тип</span>
                    <AppSelect v-model="form.type_id" :options="typeOptions" />
                </label>
            </div>

            <label class="field">
                <span>Сумма</span>
                <AppInput v-model="form.amount" type="number" min="0" step="0.01" required />
                <small class="field-hint">{{ amountHint() }}</small>
            </label>

            <label class="field">
                <span>Описание</span>
                <AppTextarea v-model="form.description" rows="4"  />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/transactions">Назад</AppButton>
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
