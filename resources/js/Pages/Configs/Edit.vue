<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    config: Object,
    users: Array,
    servers: Array,
});

const form = useForm({
    user_id: props.config.user?.id ?? '',
    description: props.config.description ?? '',
});
</script>

<template>
    <Head title="Редактирование конфига" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Редактирование конфига</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.put(submit_url)">
            <label class="field">
                <span>Сервер</span>
                <input :value="config.server.name" readonly>
            </label>

            <label class="field">
                <span>Участник</span>
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Название</span>
                <input :value="config.name" readonly>
            </label>

            <label class="field">
                <span>Адрес</span>
                <input :value="config.address" readonly>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <textarea v-model="form.description" />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/configs">Назад</Link>
            </div>
        </form>
    </section>
</template>
