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

const userOptions = props.users.map((user) => ({
    label: user.name,
    value: user.id,
}));
</script>

<template>
    <Head title="Создать токен" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создать токен</h1></div></div>

        <form class="grid" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Пользователь</span>
                <AppSelect v-model="form.user_id" :options="userOptions" />
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/user-tokens">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
