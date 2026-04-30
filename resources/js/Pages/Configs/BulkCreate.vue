<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    servers: Array,
});

const form = useForm({
    server_id: props.servers[0]?.id ?? '',
});
</script>

<template>
    <Head title="Создать конфиги" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Массовое создание конфигов</h1>
                <p>Конфиг создаётся только если у пользователя ещё нет конфига на выбранном сервере.</p>
            </div>
        </div>

        <form class="grid" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Сервер</span>
                <select v-model="form.server_id">
                    <option v-for="server in servers" :key="server.id" :value="server.id">{{ server.name }} ({{ server.ip }})</option>
                </select>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/configs">Назад</Link>
            </div>
        </form>
    </section>
</template>
