<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    users: Array,
});

const form = useForm({
    user_id: props.users[0]?.id ?? '',
});
</script>

<template>
    <Head title="Создать токен" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создать токен</h1></div></div>

        <form class="grid" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Пользователь</span>
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/user-tokens">Назад</Link>
            </div>
        </form>
    </section>
</template>
