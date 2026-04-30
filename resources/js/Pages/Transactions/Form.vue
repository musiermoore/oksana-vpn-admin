<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    transaction: Object,
    users: Array,
});

const form = useForm({
    user_id: props.transaction?.user?.id ?? props.users[0]?.id ?? '',
    amount: props.transaction?.amount ?? '',
});

const submit = () => props.mode === 'edit' ? form.put(props.submit_url) : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование транзакции' : 'Создание транзакции'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование транзакции' : 'Создание транзакции' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Участник</span>
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Сумма</span>
                <input v-model="form.amount" type="number" step="0.01" required>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/transactions">Назад</Link>
            </div>
        </form>
    </section>
</template>
